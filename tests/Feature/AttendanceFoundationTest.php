<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 1 of the attendance module: the schema, the models and the access
 * layer they sit behind. No endpoints exist yet — check-in/check-out and the
 * dashboards land in Phase 2.
 */
class AttendanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Sunday–Thursday, 09:00–17:00 with an hour's break.
     */
    private function officeSchedule(): WorkSchedule
    {
        $schedule = WorkSchedule::create(['name' => 'Office 09:00-17:00']);

        foreach ([0, 1, 2, 3, 4] as $weekday) {
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
    // Access control
    // ---------------------------------------------------------------------

    /** @test */
    public function staff_role_cannot_reach_contracts()
    {
        // The whole reason `staff` exists: design and photography now have
        // accounts so they can record attendance, and that must not hand them
        // a window onto the commercial side.
        $actor = $this->userWithRole('team');

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/contracts')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error_code' => 'FORBIDDEN']);
    }

    /** @test */
    public function staff_role_holds_no_commercial_permissions()
    {
        $staff = Role::findByName('team', 'web')->permissions->pluck('name');

        foreach (['contracts', 'companies', 'payments', 'subscriptions', 'weekly_reports'] as $group) {
            $this->assertTrue(
                $staff->filter(fn (string $name): bool => str_starts_with($name, $group.'.'))->isEmpty(),
                "The staff role must not hold any {$group}.* permission."
            );
        }
    }

    /** @test */
    public function staff_can_record_its_own_attendance_only()
    {
        $staff = Role::findByName('team', 'web');

        $this->assertTrue($staff->hasPermissionTo('attendance.view_own'));
        $this->assertTrue($staff->hasPermissionTo('attendance.create'));
        $this->assertFalse($staff->hasPermissionTo('attendance.view_all'));
        $this->assertFalse($staff->hasPermissionTo('attendance.edit'));
    }

    /** @test */
    public function managing_schedules_is_reserved_to_the_admin_accounts()
    {
        $this->assertTrue(Role::findByName('general_manager', 'web')->hasPermissionTo('attendance.manage_schedules'));

        foreach (['sales_manager', 'marketing', 'sales', 'team'] as $role) {
            $this->assertFalse(
                Role::findByName($role, 'web')->hasPermissionTo('attendance.manage_schedules'),
                "The {$role} role must not be able to redefine working weeks."
            );
        }
    }

    /** @test */
    public function every_seeded_employee_has_a_login_account()
    {
        // No account means no way to press check-in, which would silently
        // exclude someone from the attendance system.
        $this->seed(UserSeeder::class);
        $this->seed(EmployeeSeeder::class);

        $this->assertSame(
            0,
            Employee::whereNull('user_id')->count(),
            'Every employee profile must be linked to a login account.'
        );
    }

    // ---------------------------------------------------------------------
    // Schedules
    // ---------------------------------------------------------------------

    /** @test */
    public function expected_minutes_are_derived_from_the_times_not_hand_entered()
    {
        $day = WorkScheduleDay::create([
            'work_schedule_id' => WorkSchedule::create(['name' => 'Test'])->id,
            'weekday' => WorkScheduleDay::SUNDAY,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            // Deliberately wrong — the model must overwrite it.
            'expected_minutes' => 9999,
        ]);

        $this->assertSame(420, $day->fresh()->expected_minutes); // 8h - 1h break
    }

    /** @test */
    public function a_shift_crossing_midnight_does_not_produce_negative_minutes()
    {
        $day = WorkScheduleDay::create([
            'work_schedule_id' => WorkSchedule::create(['name' => 'Night'])->id,
            'weekday' => WorkScheduleDay::SATURDAY,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_minutes' => 30,
        ]);

        $this->assertSame(450, $day->fresh()->expected_minutes); // 8h - 30m
    }

    /** @test */
    public function a_weekday_without_a_row_is_simply_not_a_working_day()
    {
        // Employee C from the requirements: Monday, Tuesday, Wednesday, Saturday.
        $schedule = WorkSchedule::create(['name' => 'Partial week']);

        foreach ([WorkScheduleDay::MONDAY, WorkScheduleDay::TUESDAY, WorkScheduleDay::WEDNESDAY, WorkScheduleDay::SATURDAY] as $weekday) {
            $schedule->days()->create([
                'weekday' => $weekday,
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
            ]);
        }

        $schedule->load('days');

        $this->assertNotNull($schedule->dayFor(WorkScheduleDay::SATURDAY));
        $this->assertNotNull($schedule->dayFor(WorkScheduleDay::MONDAY));
        $this->assertNull($schedule->dayFor(WorkScheduleDay::SUNDAY));
        $this->assertNull($schedule->dayFor(WorkScheduleDay::FRIDAY));
        $this->assertSame(480, $schedule->dayFor(WorkScheduleDay::MONDAY)->expected_minutes);
    }

    /** @test */
    public function two_employees_can_start_their_week_on_different_days()
    {
        // One works Saturday–Wednesday, the other Sunday–Thursday. Nothing in
        // the system declares a company-wide week.
        $saturdayWeek = WorkSchedule::create(['name' => 'Saturday start']);
        foreach ([6, 0, 1, 2, 3] as $weekday) {
            $saturdayWeek->days()->create(['weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '17:00:00']);
        }

        $sundayWeek = WorkSchedule::create(['name' => 'Sunday start']);
        foreach ([0, 1, 2, 3, 4] as $weekday) {
            $sundayWeek->days()->create(['weekday' => $weekday, 'start_time' => '10:00:00', 'end_time' => '18:00:00']);
        }

        $this->assertNotNull($saturdayWeek->load('days')->dayFor(WorkScheduleDay::SATURDAY));
        $this->assertNull($saturdayWeek->dayFor(WorkScheduleDay::THURSDAY));

        $this->assertNull($sundayWeek->load('days')->dayFor(WorkScheduleDay::SATURDAY));
        $this->assertNotNull($sundayWeek->dayFor(WorkScheduleDay::THURSDAY));
    }

    /** @test */
    public function the_schedule_in_force_on_a_past_date_is_still_resolvable()
    {
        $employee = Employee::factory()->create();
        $old = $this->officeSchedule();
        $new = WorkSchedule::create(['name' => 'Evening 14:00-22:00']);

        // The old assignment is closed, never edited, and a new one opened.
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $old->id,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $new->id,
            'effective_from' => '2026-07-01',
            'effective_to' => null,
        ]);

        $inMarch = $employee->schedules()->covering('2026-03-15')->first();
        $inAugust = $employee->schedules()->covering('2026-08-15')->first();

        $this->assertSame($old->id, $inMarch->work_schedule_id, 'March must still resolve to the old schedule.');
        $this->assertSame($new->id, $inAugust->work_schedule_id);
        $this->assertTrue($employee->currentSchedule->isCurrent());
        $this->assertSame($new->id, $employee->currentSchedule->work_schedule_id);
    }

    /** @test */
    public function a_date_before_any_assignment_resolves_to_nothing()
    {
        $employee = Employee::factory()->create();

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $this->officeSchedule()->id,
            'effective_from' => '2026-07-01',
        ]);

        $this->assertNull($employee->schedules()->covering('2026-06-30')->first());
    }

    // ---------------------------------------------------------------------
    // Attendance rows
    // ---------------------------------------------------------------------

    /** @test */
    public function an_employee_cannot_have_two_rows_for_the_same_day()
    {
        // This database constraint — not application logic — is what makes
        // double check-ins and concurrent requests impossible.
        $employee = Employee::factory()->create();

        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-19',
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->expectException(QueryException::class);

        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-19',
            'status' => Attendance::STATUS_PRESENT,
        ]);
    }

    /** @test */
    public function the_same_day_is_free_for_a_different_employee()
    {
        $first = Employee::factory()->create();
        $second = Employee::factory()->create();

        Attendance::create(['employee_id' => $first->id, 'work_date' => '2026-09-19', 'status' => Attendance::STATUS_PRESENT]);
        Attendance::create(['employee_id' => $second->id, 'work_date' => '2026-09-19', 'status' => Attendance::STATUS_PRESENT]);

        $this->assertSame(2, Attendance::onDate('2026-09-19')->count());
    }

    /** @test */
    public function an_open_row_is_distinguished_from_a_finished_one()
    {
        $employee = Employee::factory()->create();

        $open = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-19',
            'check_in_at' => '2026-09-19 06:00:00',
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->assertTrue($open->isOpen());

        $open->update(['check_out_at' => '2026-09-19 14:00:00', 'worked_minutes' => 420]);

        $this->assertFalse($open->fresh()->isOpen());
    }

    /** @test */
    public function a_day_off_never_counts_as_a_working_day_even_when_hours_are_recorded()
    {
        $employee = Employee::factory()->create();

        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-19',
            'check_in_at' => '2026-09-19 06:00:00',
            'check_out_at' => '2026-09-19 10:00:00',
            'worked_minutes' => 240,
            'expected_minutes' => 0,
            'status' => Attendance::STATUS_DAY_OFF,
        ]);

        $this->assertTrue($row->isNonWorkingDay());
        $this->assertSame(240, $row->worked_minutes, 'Hours worked on a day off are still recorded.');
        $this->assertSame(0, $row->expected_minutes, 'But nothing was expected, so nothing is owed.');
    }

    /** @test */
    public function a_correction_is_written_to_the_audit_trail_with_the_original_value()
    {
        $actor = $this->userWithRole('general_manager');
        $this->actingAs($actor, 'sanctum');

        $employee = Employee::factory()->create();
        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-19',
            'check_in_at' => '2026-09-19 06:00:00',
            'check_out_at' => '2026-09-19 14:00:00',
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $row->update([
            'check_out_at' => '2026-09-19 14:30:00',
            'correction_reason' => 'Forgot to check out; confirmed with the team lead.',
            'source' => Attendance::SOURCE_MANUAL,
        ]);

        $entry = AuditLog::where('auditable_type', Attendance::class)
            ->where('auditable_id', $row->id)
            ->where('event', AuditLog::EVENT_UPDATED)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, 'Attendance corrections must be audited.');
        $this->assertSame($actor->id, $entry->user_id);
        $this->assertStringContainsString('14:00:00', json_encode($entry->old_values));
        $this->assertStringContainsString('14:30:00', json_encode($entry->new_values));
        $this->assertStringContainsString('Forgot to check out', json_encode($entry->new_values));
    }
}
