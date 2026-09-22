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
 * Phase 2: the self-service endpoints and the state machine behind them.
 *
 * Time is frozen per test so "today" is deterministic. Note that the clock is
 * frozen in UTC while the module resolves dates in Asia/Damascus — which is
 * precisely the seam these tests need to exercise.
 */
class AttendanceCheckInTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('team');
        $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);
    }

    /**
     * Give the employee a Sunday–Thursday 09:00–17:00 schedule with an hour break.
     */
    private function assignOfficeSchedule(array $weekdays = [0, 1, 2, 3, 4]): WorkSchedule
    {
        $schedule = WorkSchedule::create(['name' => 'Office 09:00-17:00']);

        foreach ($weekdays as $weekday) {
            $schedule->days()->create([
                'weekday' => $weekday,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
            ]);
        }

        EmployeeSchedule::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        return $schedule;
    }

    private function actAsEmployee(): self
    {
        $this->actingAs($this->user, 'sanctum');

        return $this;
    }

    // ---------------------------------------------------------------------
    // The happy path
    // ---------------------------------------------------------------------

    /** @test */
    public function an_employee_checks_in_then_out_and_the_hours_are_calculated()
    {
        $this->assignOfficeSchedule();

        // A Wednesday, 09:05 Damascus time (06:05 UTC).
        CarbonImmutable::setTestNow('2026-09-16 06:05:00');

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(201)
            ->assertJsonPath('data.state', 'working')
            ->assertJsonPath('data.can_check_in', false)
            ->assertJsonPath('data.can_check_out', true)
            ->assertJsonPath('data.attendance.check_in_time', '09:05')
            ->assertJsonPath('data.schedule.start', '09:00')
            ->assertJsonPath('data.schedule.end', '17:00');

        // 17:05 Damascus.
        CarbonImmutable::setTestNow('2026-09-16 14:05:00');

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])
            ->assertOk()
            ->assertJsonPath('data.state', 'completed')
            ->assertJsonPath('data.can_check_out', false)
            ->assertJsonPath('data.attendance.check_out_time', '17:05')
            // 8 hours on the clock, minus the scheduled 60-minute break.
            ->assertJsonPath('data.attendance.worked_minutes', 420)
            ->assertJsonPath('data.attendance.worked_hours', '7:00');

        // Asserted through the query builder rather than assertDatabaseHas:
        // SQLite keeps a `date` column as the literal string it was given, so a
        // raw-column match would compare against "2026-09-16 00:00:00" here and
        // "2026-09-16" on MariaDB.
        $this->assertTrue(
            Attendance::where('employee_id', $this->employee->id)
                ->whereDate('work_date', '2026-09-16')
                ->where('status', Attendance::STATUS_PRESENT)
                ->where('source', Attendance::SOURCE_SELF)
                ->exists()
        );
    }

    /** @test */
    public function before_checking_in_the_screen_offers_check_in_only()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 05:00:00');

        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.state', 'not_checked_in')
            ->assertJsonPath('data.can_check_in', true)
            ->assertJsonPath('data.can_check_out', false)
            ->assertJsonPath('data.is_working_day', true)
            ->assertJsonPath('data.attendance', null);
    }

    /** @test */
    public function the_response_carries_the_server_clock()
    {
        // The UI counts elapsed time against this, not against the device
        // clock, which may be wrong or deliberately set.
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:00:00');

        $response = $this->actAsEmployee()->getJson('/api/v1/attendance/today');

        $this->assertStringStartsWith('2026-09-16T09:00:00', $response->json('data.server_time'));
    }

    // ---------------------------------------------------------------------
    // The edge cases from the requirements
    // ---------------------------------------------------------------------

    /** @test */
    public function checking_in_twice_is_refused()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:00:00');

        $this->actAsEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(409)
            ->assertJson(['success' => false, 'error_code' => 'ALREADY_CHECKED_IN']);

        $this->assertSame(1, Attendance::count());
    }

    /** @test */
    public function checking_out_without_checking_in_is_refused()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:00:00');

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])
            ->assertStatus(409)
            ->assertJson(['success' => false, 'error_code' => 'NOT_CHECKED_IN']);
    }

    /** @test */
    public function checking_out_twice_is_refused_with_a_distinct_message()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:00:00');
        $this->actAsEmployee()->postJson('/api/v1/attendance/check-in');

        CarbonImmutable::setTestNow('2026-09-16 14:00:00');
        $this->actAsEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])
            ->assertStatus(409)
            ->assertJson(['success' => false, 'error_code' => 'ALREADY_CHECKED_OUT']);
    }

    /** @test */
    public function an_employee_without_a_schedule_cannot_check_in()
    {
        CarbonImmutable::setTestNow('2026-09-16 06:00:00');

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(409)
            ->assertJson(['success' => false, 'error_code' => 'NO_SCHEDULE_ASSIGNED']);

        // Reported as "no schedule", never as a day off — an unconfigured
        // employee is not on holiday, and conflating the two would send them
        // to the wrong person for a fix.
        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.state', 'no_schedule')
            ->assertJsonPath('data.has_schedule', false)
            ->assertJsonPath('data.can_check_in', false);
    }

    /** @test */
    public function an_account_with_no_employee_profile_is_told_plainly()
    {
        $orphan = User::factory()->create();
        $orphan->assignRole('team');

        $this->actingAs($orphan, 'sanctum')
            ->getJson('/api/v1/attendance/today')
            ->assertStatus(409)
            ->assertJson(['success' => false, 'error_code' => 'NO_EMPLOYEE_PROFILE']);
    }

    /** @test */
    public function a_forgotten_check_out_from_days_ago_blocks_rather_than_recording_an_impossible_shift()
    {
        $this->assignOfficeSchedule();

        $stale = Attendance::create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-10',
            'check_in_at' => '2026-09-10 06:00:00',
            'status' => Attendance::STATUS_INCOMPLETE,
        ]);

        // A day is only open if it has an open sitting — that is what
        // check-out looks for.
        $stale->sessions()->create([
            'sequence' => 1,
            'check_in_at' => '2026-09-10 06:00:00',
            'check_out_at' => null,
        ]);

        CarbonImmutable::setTestNow('2026-09-16 14:00:00');

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])
            ->assertStatus(409)
            ->assertJson(['success' => false, 'error_code' => 'STALE_OPEN_ATTENDANCE']);
    }

    /** @test */
    public function a_shift_running_past_midnight_can_still_be_closed()
    {
        // Check-out is found by open-ness, not by today's date, so the night
        // shift is not orphaned at 00:00.
        $this->assignOfficeSchedule([0, 1, 2, 3, 4, 5, 6]);

        CarbonImmutable::setTestNow('2026-09-16 20:00:00'); // 23:00 Damascus
        $this->actAsEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        CarbonImmutable::setTestNow('2026-09-16 23:30:00'); // 02:30 next day
        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])
            ->assertOk()
            ->assertJsonPath('data.attendance.work_date', '2026-09-16');
    }

    // ---------------------------------------------------------------------
    // Non-working days
    // ---------------------------------------------------------------------

    /** @test */
    public function a_day_off_is_reported_as_such_and_earns_no_expected_hours()
    {
        // Sunday–Thursday schedule; 2026-09-18 is a Friday.
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-18 06:00:00');

        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.state', Attendance::STATUS_DAY_OFF)
            ->assertJsonPath('data.is_working_day', false)
            // Recording is still allowed — people do come in on a day off.
            ->assertJsonPath('data.can_check_in', true);

        $this->actAsEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        CarbonImmutable::setTestNow('2026-09-18 10:00:00');
        $this->actAsEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();

        $row = Attendance::first();

        $this->assertSame(Attendance::STATUS_DAY_OFF, $row->status, 'A day off stays a day off even when worked.');
        $this->assertSame(240, $row->worked_minutes, 'The hours are recorded.');
        $this->assertSame(0, $row->expected_minutes, 'But nothing was expected, so nothing is owed.');
        $this->assertSame(0, $row->break_minutes);
    }

    /** @test */
    public function a_public_holiday_is_not_a_working_day()
    {
        $this->assignOfficeSchedule();
        Holiday::create(['date' => '2026-09-16', 'name' => 'National holiday']);

        CarbonImmutable::setTestNow('2026-09-16 06:00:00');

        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.state', Attendance::STATUS_HOLIDAY);
    }

    /** @test */
    public function a_recurring_holiday_matches_in_later_years()
    {
        $this->assignOfficeSchedule([0, 1, 2, 3, 4, 5, 6]);
        Holiday::create(['date' => '2020-01-01', 'name' => 'New Year', 'is_recurring' => true]);

        CarbonImmutable::setTestNow('2027-01-01 06:00:00');

        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.state', Attendance::STATUS_HOLIDAY);
    }

    /** @test */
    public function an_approved_leave_shows_as_leave_but_a_pending_one_does_not()
    {
        $this->assignOfficeSchedule();

        $leave = EmployeeLeave::create([
            'employee_id' => $this->employee->id,
            'leave_type' => 'annual',
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-17',
            'days_count' => 4,
            'status' => 'pending',
        ]);

        CarbonImmutable::setTestNow('2026-09-16 06:00:00');

        // A request that has not been approved does not excuse the day.
        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.state', 'not_checked_in');

        $leave->update(['status' => 'approved']);

        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.state', Attendance::STATUS_LEAVE);
    }

    // ---------------------------------------------------------------------
    // Security
    // ---------------------------------------------------------------------

    /** @test */
    public function a_client_supplied_time_employee_or_status_is_ignored()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:00:00');

        $victim = Employee::factory()->create();

        $this->actAsEmployee()->postJson('/api/v1/attendance/check-in', [
            'employee_id' => $victim->id,
            'check_in_at' => '2026-09-16 04:00:00',
            'work_date' => '2026-09-01',
            'status' => Attendance::STATUS_PRESENT,
            'worked_minutes' => 9999,
        ])->assertStatus(201);

        $row = Attendance::first();

        $this->assertSame($this->employee->id, $row->employee_id, 'The employee comes from the token, never the body.');
        $this->assertSame('2026-09-16', $row->work_date->format('Y-m-d'));
        $this->assertSame('09:00', $row->check_in_at->timezone(config('attendance.timezone'))->format('H:i'));
        $this->assertSame(0, $row->worked_minutes);
        $this->assertSame(0, Attendance::where('employee_id', $victim->id)->count());
    }

    /** @test */
    public function an_account_without_the_create_permission_cannot_check_in()
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('attendance.view_own');
        Employee::factory()->create(['user_id' => $viewer->id]);

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error_code' => 'FORBIDDEN']);
    }

    /** @test */
    public function an_unauthenticated_request_is_rejected()
    {
        $this->postJson('/api/v1/attendance/check-in')->assertStatus(401);
        $this->getJson('/api/v1/attendance/today')->assertStatus(401);
    }

    /** @test */
    public function a_disabled_account_cannot_check_in()
    {
        $this->assignOfficeSchedule();
        $this->user->update(['is_active' => false]);

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // History
    // ---------------------------------------------------------------------

    /** @test */
    public function the_history_endpoint_returns_a_range_with_its_totals()
    {
        $this->assignOfficeSchedule();

        foreach ([['2026-09-14', 420], ['2026-09-15', 400], ['2026-09-16', 435]] as [$date, $minutes]) {
            Attendance::create([
                'employee_id' => $this->employee->id,
                'work_date' => $date,
                'check_in_at' => $date.' 06:00:00',
                'check_out_at' => $date.' 14:00:00',
                'worked_minutes' => $minutes,
                'expected_minutes' => 420,
                'status' => Attendance::STATUS_PRESENT,
            ]);
        }

        Attendance::create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-17',
            'status' => Attendance::STATUS_ABSENT,
            'expected_minutes' => 420,
        ]);

        CarbonImmutable::setTestNow('2026-09-18 06:00:00');

        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/my?from=2026-09-14&to=2026-09-17')
            ->assertOk()
            ->assertJsonCount(4, 'data.records')
            ->assertJsonPath('data.summary.present_days', 3)
            ->assertJsonPath('data.summary.absent_days', 1)
            ->assertJsonPath('data.summary.worked_minutes', 1255)
            // Newest first.
            ->assertJsonPath('data.records.0.work_date', '2026-09-17');
    }

    /** @test */
    public function history_never_leaks_another_employees_records()
    {
        $this->assignOfficeSchedule();

        $other = Employee::factory()->create();
        Attendance::create([
            'employee_id' => $other->id,
            'work_date' => '2026-09-16',
            'status' => Attendance::STATUS_PRESENT,
            'worked_minutes' => 480,
        ]);

        CarbonImmutable::setTestNow('2026-09-16 06:00:00');

        $this->actAsEmployee()
            ->getJson('/api/v1/attendance/my?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(0, 'data.records');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // What was done today
    // ---------------------------------------------------------------------

    /** @test */
    public function checking_out_without_saying_what_was_done_is_refused()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:05:00');
        $this->actAsEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        CarbonImmutable::setTestNow('2026-09-16 14:05:00');

        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-out')
            ->assertStatus(422)
            ->assertJsonValidationErrors('notes');

        // A single character is the reflex a minimum length is there to stop.
        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-out', ['notes' => '.'])
            ->assertStatus(422);
    }

    /** @test */
    public function what_was_done_reaches_the_management_grid()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:05:00');
        $this->actAsEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        CarbonImmutable::setTestNow('2026-09-16 14:05:00');
        $this->actAsEmployee()
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the client brochure.'])
            ->assertOk();

        $admin = User::factory()->create();
        $admin->assignRole('general_manager');

        // The answer is stored on the sitting, not the day's row, so the grid
        // has to load the sessions to be able to show it at all.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/attendance?date=2026-09-16')
            ->assertOk()
            ->assertJsonPath('data.records.0.tasks', 'Finished the client brochure.');
    }

    /** @test */
    public function a_day_nobody_recorded_reports_no_tasks_rather_than_omitting_the_field()
    {
        $this->assignOfficeSchedule();

        $admin = User::factory()->create();
        $admin->assignRole('general_manager');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/attendance?date=2026-09-16')
            ->assertOk()
            ->assertJsonPath('data.records.0.tasks', null);
    }
}
