<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3: schedule configuration, holidays, and the nightly close.
 */
class AttendanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('general_manager');

        CarbonImmutable::setTestNow('2026-09-16 10:00:00'); // Wednesday
    }

    private function asAdmin(): self
    {
        $this->actingAs($this->admin, 'sanctum');

        return $this;
    }

    private function officeSchedule(array $weekdays = [0, 1, 2, 3, 4]): WorkSchedule
    {
        $schedule = WorkSchedule::create(['name' => 'Office']);

        foreach ($weekdays as $weekday) {
            $schedule->days()->create([
                'weekday' => $weekday,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
            ]);
        }

        return $schedule;
    }

    // ---------------------------------------------------------------------
    // Schedule templates
    // ---------------------------------------------------------------------

    /** @test */
    public function a_schedule_is_created_with_its_working_days()
    {
        $this->asAdmin()
            ->postJson('/api/v1/work-schedules', [
                'name' => 'Evening shift',
                'days' => [
                    ['weekday' => 6, 'start_time' => '14:00', 'end_time' => '22:00'],
                    ['weekday' => 0, 'start_time' => '14:00', 'end_time' => '22:00', 'break_minutes' => 30],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Evening shift')
            ->assertJsonPath('data.working_days_count', 2)
            // 8h + 7.5h — derived, never taken from the client.
            ->assertJsonPath('data.weekly_minutes', 930)
            // Saturday first, per config('attendance.week_start').
            ->assertJsonPath('data.days.0.weekday', 6);
    }

    /** @test */
    public function updating_a_schedule_replaces_its_week_but_leaves_history_alone()
    {
        $employee = Employee::factory()->create();
        $schedule = $this->officeSchedule();

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        // A day already recorded under the old times.
        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-15',
            'scheduled_start' => '09:00:00',
            'scheduled_end' => '17:00:00',
            'expected_minutes' => 420,
            'status' => Attendance::STATUS_PRESENT,
            'work_schedule_id' => $schedule->id,
        ]);

        $this->asAdmin()
            ->putJson("/api/v1/work-schedules/{$schedule->id}", [
                'name' => 'Office (new hours)',
                'days' => [['weekday' => 1, 'start_time' => '11:00', 'end_time' => '19:00']],
            ])
            ->assertOk()
            ->assertJsonPath('data.working_days_count', 1);

        // The recorded day still says what it always said.
        $row->refresh();
        $this->assertSame('09:00:00', $row->scheduled_start);
        $this->assertSame(420, $row->expected_minutes);
    }

    /** @test */
    public function a_schedule_still_assigned_to_someone_cannot_be_deleted()
    {
        $employee = Employee::factory()->create();
        $schedule = $this->officeSchedule();

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        $this->asAdmin()
            ->deleteJson("/api/v1/work-schedules/{$schedule->id}")
            ->assertStatus(409)
            ->assertJson(['error_code' => 'WORK_SCHEDULE_IN_USE']);

        $this->assertSame(1, WorkSchedule::count());
    }

    /** @test */
    public function an_unused_schedule_can_be_deleted()
    {
        $schedule = $this->officeSchedule();

        $this->asAdmin()->deleteJson("/api/v1/work-schedules/{$schedule->id}")->assertOk();

        $this->assertSame(0, WorkSchedule::count());
    }

    // ---------------------------------------------------------------------
    // Assignment
    // ---------------------------------------------------------------------

    /** @test */
    public function assigning_a_new_schedule_closes_the_previous_one_instead_of_editing_it()
    {
        $employee = Employee::factory()->create();
        $old = $this->officeSchedule();
        $new = WorkSchedule::create(['name' => 'Evening']);

        $this->asAdmin()->postJson("/api/v1/employees/{$employee->id}/schedules", [
            'work_schedule_id' => $old->id,
            'effective_from' => '2026-01-01',
        ])->assertStatus(201);

        $this->asAdmin()->postJson("/api/v1/employees/{$employee->id}/schedules", [
            'work_schedule_id' => $new->id,
            'effective_from' => '2026-07-01',
        ])->assertStatus(201)->assertJsonPath('data.is_current', true);

        $assignments = $employee->schedules()->get();

        $this->assertCount(2, $assignments, 'The old assignment is kept, not overwritten.');
        $this->assertSame('2026-06-30', $assignments->firstWhere('work_schedule_id', $old->id)->effective_to->format('Y-m-d'));
        $this->assertNull($assignments->firstWhere('work_schedule_id', $new->id)->effective_to);

        // And a past date still resolves to the schedule that applied then.
        $this->assertSame($old->id, $employee->schedules()->covering('2026-03-01')->first()->work_schedule_id);
    }

    /** @test */
    public function a_new_assignment_cannot_start_before_the_current_one_began()
    {
        $employee = Employee::factory()->create();
        $schedule = $this->officeSchedule();

        $this->asAdmin()->postJson("/api/v1/employees/{$employee->id}/schedules", [
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2026-07-01',
        ])->assertStatus(201);

        $this->asAdmin()->postJson("/api/v1/employees/{$employee->id}/schedules", [
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2026-05-01',
        ])
            ->assertStatus(409)
            ->assertJson(['error_code' => 'SCHEDULE_OVERLAP']);
    }

    /** @test */
    public function the_assignment_history_is_readable()
    {
        $employee = Employee::factory()->create();
        $schedule = $this->officeSchedule();

        $this->asAdmin()->postJson("/api/v1/employees/{$employee->id}/schedules", [
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2026-01-01',
        ]);

        $this->asAdmin()
            ->getJson("/api/v1/employees/{$employee->id}/schedules")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.0.work_schedule.name', 'Office');
    }

    // ---------------------------------------------------------------------
    // Holidays
    // ---------------------------------------------------------------------

    /** @test */
    public function holidays_are_managed_and_cannot_be_duplicated()
    {
        $this->asAdmin()
            ->postJson('/api/v1/holidays', ['date' => '2026-12-25', 'name' => 'Christmas', 'is_recurring' => true])
            ->assertStatus(201)
            ->assertJsonPath('data.is_recurring', true);

        $this->asAdmin()
            ->postJson('/api/v1/holidays', ['date' => '2026-12-25', 'name' => 'Duplicate'])
            ->assertStatus(409)
            ->assertJson(['error_code' => 'HOLIDAY_ALREADY_EXISTS']);

        $this->asAdmin()->getJson('/api/v1/holidays')->assertOk()->assertJsonCount(1, 'data');
    }

    /** @test */
    public function only_admins_may_configure_schedules_and_holidays()
    {
        foreach (['sales_manager', 'marketing', 'sales', 'team'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/work-schedules')
                ->assertStatus(403, "The {$role} role must not reach schedule configuration.");

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/holidays', ['date' => '2026-12-25', 'name' => 'x'])
                ->assertStatus(403);
        }
    }

    // ---------------------------------------------------------------------
    // The nightly close
    // ---------------------------------------------------------------------

    /** @test */
    public function closing_a_day_marks_forgotten_check_outs_and_records_absences()
    {
        $schedule = $this->officeSchedule();

        $forgot = $this->employeeOn($schedule, 'Forgot');
        $absent = $this->employeeOn($schedule, 'Absent');
        $fine = $this->employeeOn($schedule, 'Fine');

        // Yesterday, 2026-09-15 (a Tuesday — a working day).
        Attendance::create([
            'employee_id' => $forgot->id,
            'work_date' => '2026-09-15',
            'check_in_at' => '2026-09-15 06:00:00',
            'status' => Attendance::STATUS_PRESENT,
        ]);
        Attendance::create([
            'employee_id' => $fine->id,
            'work_date' => '2026-09-15',
            'check_in_at' => '2026-09-15 06:00:00',
            'check_out_at' => '2026-09-15 14:00:00',
            'worked_minutes' => 420,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->artisan('attendance:close-day')
            ->expectsOutputToContain('Closed 2026-09-15: 1 incomplete, 1 absent.')
            ->assertSuccessful();

        $this->assertSame(
            Attendance::STATUS_INCOMPLETE,
            Attendance::where('employee_id', $forgot->id)->first()->status
        );
        // No departure time was invented.
        $this->assertNull(Attendance::where('employee_id', $forgot->id)->first()->check_out_at);

        $absentRow = Attendance::where('employee_id', $absent->id)->first();
        $this->assertSame(Attendance::STATUS_ABSENT, $absentRow->status);
        $this->assertSame(Attendance::SOURCE_SYSTEM, $absentRow->source);
        $this->assertSame(420, $absentRow->expected_minutes, 'The snapshot is taken even for an absence.');

        // The complete day is untouched.
        $this->assertSame(
            Attendance::STATUS_PRESENT,
            Attendance::where('employee_id', $fine->id)->first()->status
        );
    }

    /** @test */
    public function closing_a_day_never_marks_a_day_off_holiday_or_leave_as_absent()
    {
        $schedule = $this->officeSchedule();
        $onLeave = $this->employeeOn($schedule, 'On Leave');
        $this->employeeOn($schedule, 'Ordinary');

        EmployeeLeave::create([
            'employee_id' => $onLeave->id,
            'leave_type' => 'annual',
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-16',
            'days_count' => 3,
            'status' => 'approved',
        ]);

        // 2026-09-18 is a Friday — outside the Sunday–Thursday week.
        CarbonImmutable::setTestNow('2026-09-19 10:00:00');
        $this->artisan('attendance:close-day')->assertSuccessful();
        $this->assertSame(0, Attendance::count(), 'A day off produces no rows at all.');

        // A holiday on a working day is likewise not an absence.
        Holiday::create(['date' => '2026-09-15', 'name' => 'Public holiday']);
        CarbonImmutable::setTestNow('2026-09-16 10:00:00');
        $this->artisan('attendance:close-day')->assertSuccessful();

        $this->assertSame(0, Attendance::where('status', Attendance::STATUS_ABSENT)->count());
    }

    /** @test */
    public function a_leave_day_is_not_recorded_as_an_absence()
    {
        $schedule = $this->officeSchedule();
        $onLeave = $this->employeeOn($schedule, 'On Leave');

        EmployeeLeave::create([
            'employee_id' => $onLeave->id,
            'leave_type' => 'annual',
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-15',
            'days_count' => 1,
            'status' => 'approved',
        ]);

        $this->artisan('attendance:close-day')->assertSuccessful();

        $this->assertSame(0, Attendance::count());
    }

    /** @test */
    public function the_command_refuses_to_close_a_day_that_is_not_over()
    {
        $this->artisan('attendance:close-day', ['--date' => '2026-09-16'])
            ->expectsOutputToContain('the day is not over yet')
            ->assertFailed();
    }

    /** @test */
    public function a_dry_run_changes_nothing()
    {
        $schedule = $this->officeSchedule();
        $this->employeeOn($schedule, 'Absent');

        $this->artisan('attendance:close-day', ['--dry-run' => true])
            ->expectsOutputToContain('Would close 2026-09-15: 0 incomplete, 1 absent.')
            ->assertSuccessful();

        $this->assertSame(0, Attendance::count());
    }

    /**
     * An employee attached to a schedule and holding a login account.
     */
    private function employeeOn(WorkSchedule $schedule, string $name): Employee
    {
        $employee = Employee::factory()->create(['name' => $name]);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        return $employee;
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
