<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3: the management dashboard, the employee calendar and corrections.
 */
class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('general_manager');

        // A Wednesday.
        CarbonImmutable::setTestNow('2026-09-16 10:00:00');
    }

    /**
     * An employee on a Sunday–Thursday 09:00–17:00 schedule.
     */
    private function employeeWithSchedule(string $name, string $department = 'design'): Employee
    {
        $employee = Employee::factory()->create(['name' => $name, 'department' => $department]);

        $schedule = WorkSchedule::firstOrCreate(['name' => 'Office 09:00-17:00']);

        if ($schedule->days()->count() === 0) {
            foreach ([0, 1, 2, 3, 4] as $weekday) {
                $schedule->days()->create([
                    'weekday' => $weekday,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'break_minutes' => 60,
                ]);
            }
        }

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        return $employee;
    }

    private function asAdmin(): self
    {
        $this->actingAs($this->admin, 'sanctum');

        return $this;
    }

    // ---------------------------------------------------------------------
    // The dashboard
    // ---------------------------------------------------------------------

    /** @test */
    public function the_overview_counts_the_day()
    {
        $working = $this->employeeWithSchedule('Working Now');
        $done = $this->employeeWithSchedule('Finished');
        $this->employeeWithSchedule('Not Arrived');
        $unconfigured = Employee::factory()->create(['name' => 'No Schedule']);

        Attendance::create([
            'employee_id' => $working->id,
            'work_date' => '2026-09-16',
            'check_in_at' => '2026-09-16 06:00:00',
            'status' => Attendance::STATUS_PRESENT,
        ]);
        Attendance::create([
            'employee_id' => $done->id,
            'work_date' => '2026-09-16',
            'check_in_at' => '2026-09-16 06:00:00',
            'check_out_at' => '2026-09-16 14:00:00',
            'worked_minutes' => 420,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->asAdmin()
            ->getJson('/api/v1/attendance/overview')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-09-16')
            ->assertJsonPath('data.total_employees', 4)
            ->assertJsonPath('data.checked_in', 2)
            ->assertJsonPath('data.currently_working', 1)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.not_checked_in', 1)
            // The unconfigured employee shows as a day off, and is counted
            // separately so management can see what needs setting up.
            ->assertJsonPath('data.without_schedule', 1);

        $this->assertNotNull($unconfigured->id);
    }

    /** @test */
    public function the_grid_derives_days_that_were_never_recorded()
    {
        // Nothing is stored for a day off, a holiday or a leave — the reader
        // fills them in, so the table shows a complete company.
        $onLeave = $this->employeeWithSchedule('On Leave');
        $this->employeeWithSchedule('Ordinary');

        EmployeeLeave::create([
            'employee_id' => $onLeave->id,
            'leave_type' => 'annual',
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-17',
            'days_count' => 3,
            'status' => 'approved',
        ]);

        $response = $this->asAdmin()->getJson('/api/v1/attendance')->assertOk();

        $this->assertCount(2, $response->json('data.records'));
        $this->assertSame(0, Attendance::count(), 'Derived days must not be written to the table.');

        $statuses = collect($response->json('data.records'))->pluck('status', 'employee_name');
        $this->assertSame(Attendance::STATUS_LEAVE, $statuses['On Leave']);
        // Today, before any check-in: not an absence yet, and not a presence.
        $this->assertSame(Attendance::STATUS_PENDING, $statuses['Ordinary']);
    }

    /** @test */
    public function the_grid_filters_by_employee_department_and_status()
    {
        $designer = $this->employeeWithSchedule('Designer', 'design');
        $this->employeeWithSchedule('Seller', 'sales');

        $this->asAdmin()->getJson('/api/v1/attendance?department=sales')
            ->assertOk()->assertJsonCount(1, 'data.records')
            ->assertJsonPath('data.records.0.employee_name', 'Seller');

        $this->asAdmin()->getJson('/api/v1/attendance?employee_id='.$designer->id)
            ->assertOk()->assertJsonCount(1, 'data.records')
            ->assertJsonPath('data.records.0.employee_name', 'Designer');

        $this->asAdmin()->getJson('/api/v1/attendance?status=pending')
            ->assertOk()->assertJsonCount(2, 'data.records');

        $this->asAdmin()->getJson('/api/v1/attendance?status=present')
            ->assertOk()->assertJsonCount(0, 'data.records');
    }

    /** @test */
    public function an_explicit_date_can_be_inspected()
    {
        $employee = $this->employeeWithSchedule('Someone');

        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-14',
            'check_in_at' => '2026-09-14 06:00:00',
            'check_out_at' => '2026-09-14 14:00:00',
            'worked_minutes' => 420,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->asAdmin()->getJson('/api/v1/attendance?date=2026-09-14')
            ->assertOk()
            ->assertJsonPath('data.records.0.status', Attendance::STATUS_PRESENT)
            ->assertJsonPath('data.records.0.worked_hours', '7:00');
    }

    // ---------------------------------------------------------------------
    // One employee's calendar
    // ---------------------------------------------------------------------

    /** @test */
    public function an_employees_month_is_a_complete_calendar_with_totals()
    {
        $employee = $this->employeeWithSchedule('Monthly');

        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-14',
            'check_in_at' => '2026-09-14 06:00:00',
            'check_out_at' => '2026-09-14 14:00:00',
            'worked_minutes' => 420,
            'expected_minutes' => 420,
            'status' => Attendance::STATUS_PRESENT,
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-15',
            'status' => Attendance::STATUS_ABSENT,
            'expected_minutes' => 420,
        ]);

        $response = $this->asAdmin()
            ->getJson("/api/v1/employees/{$employee->id}/attendance?month=2026-09")
            ->assertOk()
            ->assertJsonPath('data.employee.name', 'Monthly')
            ->assertJsonPath('data.summary.present_days', 1)
            ->assertJsonPath('data.summary.worked_minutes', 420)
            // September 2026 has 4 Fridays and 4 Saturdays, so a Sunday–Thursday
            // schedule leaves 22 working days out of 30.
            ->assertJsonPath('data.summary.working_days', 22)
            ->assertJsonPath('data.summary.day_off_days', 8)
            // One stored absence (the 15th) plus every past working day with no
            // record at all — the same days attendance:close-day would have
            // written. Future days are not absences; today is not one yet.
            ->assertJsonPath('data.summary.absent_days', 10);

        // Every day of September, present in the calendar whether recorded or not.
        $this->assertCount(30, $response->json('data.records'));
        $this->assertSame('2026-09-30', $response->json('data.records.0.work_date'), 'Newest first.');

        $statuses = collect($response->json('data.records'))->pluck('status', 'work_date');
        $this->assertSame(Attendance::STATUS_PENDING, $statuses['2026-09-16'], 'Today is not an absence yet.');
        $this->assertSame(Attendance::STATUS_PENDING, $statuses['2026-09-17'], 'Nor is a future working day.');
        $this->assertSame(Attendance::STATUS_DAY_OFF, $statuses['2026-09-18'], 'A Friday is a day off.');
    }

    /** @test */
    public function an_employee_may_open_their_own_calendar_but_not_a_colleagues()
    {
        $mine = $this->employeeWithSchedule('Mine');
        $theirs = $this->employeeWithSchedule('Theirs');

        $staff = User::factory()->create();
        $staff->assignRole('team');
        $mine->update(['user_id' => $staff->id]);

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/v1/employees/{$mine->id}/attendance?month=2026-09")
            ->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/v1/employees/{$theirs->id}/attendance?month=2026-09")
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // Corrections
    // ---------------------------------------------------------------------

    /** @test */
    public function a_correction_requires_a_reason()
    {
        $employee = $this->employeeWithSchedule('Forgetful');
        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-15',
            'check_in_at' => '2026-09-15 06:00:00',
            'status' => Attendance::STATUS_INCOMPLETE,
            'break_minutes' => 60,
        ]);

        $this->asAdmin()
            ->putJson("/api/v1/attendance/{$row->id}", ['check_out_time' => '17:30'])
            ->assertStatus(422)
            ->assertJsonPath('errors.reason.0', 'A reason is required: corrections are recorded in the audit trail alongside the original value.');
    }

    /** @test */
    public function a_correction_recomputes_the_hours_and_records_the_original_value()
    {
        $employee = $this->employeeWithSchedule('Forgetful');
        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-15',
            'check_in_at' => '2026-09-15 06:00:00', // 09:00 Damascus
            'status' => Attendance::STATUS_INCOMPLETE,
            'break_minutes' => 60,
            'expected_minutes' => 420,
        ]);

        $this->asAdmin()
            ->putJson("/api/v1/attendance/{$row->id}", [
                'check_out_time' => '17:30',
                'reason' => 'Forgot to check out; confirmed with the team lead.',
            ])
            ->assertOk()
            ->assertJsonPath('data.check_out_time', '17:30')
            ->assertJsonPath('data.status', Attendance::STATUS_PRESENT)
            // 09:00–17:30 is 8h30m, less the 60-minute break.
            ->assertJsonPath('data.worked_minutes', 450)
            ->assertJsonPath('data.source', Attendance::SOURCE_MANUAL);

        $entry = AuditLog::where('auditable_type', Attendance::class)
            ->where('auditable_id', $row->id)
            ->where('event', AuditLog::EVENT_UPDATED)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($this->admin->id, $entry->user_id);
        $this->assertNull($entry->old_values['check_out_at'], 'The original value — empty — is preserved.');
        $this->assertStringContainsString('Forgot to check out', json_encode($entry->new_values));
        $this->assertSame(Attendance::STATUS_INCOMPLETE, $entry->old_values['status']);
    }

    /** @test */
    public function correcting_a_day_off_does_not_turn_it_into_a_working_day()
    {
        $employee = $this->employeeWithSchedule('Weekender');
        // 2026-09-18 is a Friday — not in the Sunday–Thursday schedule.
        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-18',
            'check_in_at' => '2026-09-18 06:00:00',
            'status' => Attendance::STATUS_DAY_OFF,
            'expected_minutes' => 0,
        ]);

        $this->asAdmin()
            ->putJson("/api/v1/attendance/{$row->id}", [
                'check_out_time' => '13:00',
                'reason' => 'Came in to finish a delivery.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Attendance::STATUS_DAY_OFF)
            ->assertJsonPath('data.worked_minutes', 240)
            ->assertJsonPath('data.expected_minutes', 0);
    }

    /** @test */
    public function management_can_enter_a_record_by_hand()
    {
        $employee = $this->employeeWithSchedule('Absentee');

        $this->asAdmin()
            ->postJson('/api/v1/attendance', [
                'employee_id' => $employee->id,
                'work_date' => '2026-09-15',
                'check_in_time' => '09:00',
                'check_out_time' => '17:00',
                'reason' => 'Machine was offline; recorded from the paper sheet.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.source', Attendance::SOURCE_MANUAL)
            ->assertJsonPath('data.worked_minutes', 420)
            ->assertJsonPath('data.check_in_time', '09:00');
    }

    /** @test */
    public function a_manual_entry_cannot_duplicate_an_existing_day()
    {
        $employee = $this->employeeWithSchedule('Taken');
        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-15',
            'status' => Attendance::STATUS_ABSENT,
        ]);

        $this->asAdmin()
            ->postJson('/api/v1/attendance', [
                'employee_id' => $employee->id,
                'work_date' => '2026-09-15',
                'reason' => 'Trying again.',
            ])
            ->assertStatus(409)
            ->assertJson(['error_code' => 'ATTENDANCE_ALREADY_EXISTS']);
    }

    /** @test */
    public function a_record_can_be_deleted()
    {
        $employee = $this->employeeWithSchedule('Doomed');
        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-15',
            'status' => Attendance::STATUS_ABSENT,
        ]);

        $this->asAdmin()->deleteJson("/api/v1/attendance/{$row->id}")->assertOk();

        $this->assertSame(0, Attendance::count());
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    /** @test */
    public function staff_cannot_see_the_company_dashboard_or_correct_anything()
    {
        $employee = $this->employeeWithSchedule('Nosy');
        $staff = User::factory()->create();
        $staff->assignRole('team');
        $employee->update(['user_id' => $staff->id]);

        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-15',
            'check_in_at' => '2026-09-15 06:00:00',
            'status' => Attendance::STATUS_INCOMPLETE,
        ]);

        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/attendance')->assertStatus(403);
        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/attendance/overview')->assertStatus(403);
        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/attendance', [])->assertStatus(403);
        $this->actingAs($staff, 'sanctum')->deleteJson("/api/v1/attendance/{$row->id}")->assertStatus(403);

        // The one that matters most: an employee must not be able to rewrite
        // their own record.
        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/v1/attendance/{$row->id}", ['check_out_time' => '23:00', 'reason' => 'nice try'])
            ->assertStatus(403);
    }

    /** @test */
    public function a_sales_manager_may_read_everything_but_not_correct_or_configure()
    {
        // Reading the grid and rewriting it are different powers. The sales
        // manager watches their team's attendance; redefining a working week or
        // correcting a punched record stays above them.
        $manager = User::factory()->create();
        $manager->assignRole('sales_manager');
        $this->employeeWithSchedule('Watched');

        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/attendance')->assertOk();
        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/attendance/overview')->assertOk();
        $this->actingAs($manager, 'sanctum')->postJson('/api/v1/attendance', [])->assertStatus(403);
        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/work-schedules')->assertStatus(403);
        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/holidays')->assertStatus(403);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function the_team_summary_reports_one_row_per_employee_for_a_range()
    {
        $this->employeeWithSchedule('Hala', 'design');
        $this->employeeWithSchedule('Zain', 'design');

        $response = $this->asAdmin()
            ->getJson('/api/v1/attendance/summary?from=2026-09-14&to=2026-09-18')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-09-14')
            ->assertJsonPath('data.to', '2026-09-18');

        $records = $response->json('data.records');

        // One row per tracked employee — not one per day of the range.
        $this->assertCount(2, $records);
        $this->assertSame(
            count($records),
            count(array_unique(array_column($records, 'employee_id')))
        );

        // The same totals one employee's own calendar reports.
        $this->assertArrayHasKey('worked_minutes', $records[0]);
        $this->assertArrayHasKey('absent_days', $records[0]);
        $this->assertSame('Hala', $records[0]['employee_name']);
    }

    /** @test */
    public function the_team_summary_can_be_narrowed_to_one_department()
    {
        $this->employeeWithSchedule('Hala', 'design');
        $this->employeeWithSchedule('Omar', 'dev');

        $records = $this->asAdmin()
            ->getJson('/api/v1/attendance/summary?from=2026-09-14&to=2026-09-18&department=design')
            ->assertOk()
            ->json('data.records');

        $this->assertCount(1, $records);
        $this->assertSame('design', $records[0]['department']);
    }

    /** @test */
    public function the_team_summary_is_closed_to_someone_without_view_all()
    {
        $user = User::factory()->create();
        $user->assignRole('team');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/summary?from=2026-09-14&to=2026-09-18')
            ->assertForbidden();
    }
}
