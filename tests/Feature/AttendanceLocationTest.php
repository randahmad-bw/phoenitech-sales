<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three facts added on 2026-09-20 from management's real schedules:
 * per-day work location, open-ended days, and employees outside the module.
 */
class AttendanceLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('general_manager');

        CarbonImmutable::setTestNow('2026-09-20 07:00:00'); // Sunday, 10:00 Damascus
    }

    private function asAdmin(): self
    {
        $this->actingAs($this->admin, 'sanctum');

        return $this;
    }

    private function employeeWithDays(array $days, string $name = 'Someone'): Employee
    {
        $employee = Employee::factory()->create(['name' => $name, 'department' => 'design']);
        $schedule = WorkSchedule::create(['name' => "Schedule for {$name}"]);

        foreach ($days as $day) {
            $schedule->days()->create($day);
        }

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        return $employee;
    }

    // ---------------------------------------------------------------------
    // Location is per day, not per employee
    // ---------------------------------------------------------------------

    /** @test */
    public function the_same_employee_can_be_in_the_office_one_day_and_remote_the_next()
    {
        // Zain: office Saturday and Sunday, remote Monday to Wednesday.
        $zain = $this->employeeWithDays([
            ['weekday' => 6, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
            ['weekday' => 0, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
            ['weekday' => 1, 'location' => 'remote', 'start_time' => '08:00', 'end_time' => '13:00'],
            ['weekday' => 2, 'location' => 'remote', 'start_time' => '08:00', 'end_time' => '13:00'],
            ['weekday' => 3, 'location' => 'remote', 'start_time' => '08:00', 'end_time' => '13:00'],
        ], 'Zain');

        $user = User::factory()->create();
        $user->assignRole('team');
        $zain->update(['user_id' => $user->id]);

        // Sunday — office.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.schedule.location', 'office');

        // Monday — remote. Same person, same week, different place.
        CarbonImmutable::setTestNow('2026-09-21 07:00:00');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.schedule.location', 'remote');
    }

    /** @test */
    public function the_location_is_snapshotted_onto_the_record_at_check_in()
    {
        // Today is Sunday (weekday 0), worked remotely.
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'remote', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(201)
            ->assertJsonPath('data.attendance.location', 'remote');

        $this->assertSame('remote', Attendance::first()->location);
    }

    /** @test */
    public function moving_someone_to_the_office_does_not_rewrite_where_they_already_worked()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 6, 'location' => 'remote', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-19', // Saturday
            'location' => 'remote',
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $schedule = $employee->currentSchedule->workSchedule;
        $schedule->days()->update(['location' => 'office']);

        $this->assertSame('remote', $row->fresh()->location, 'History keeps the location that applied then.');
    }

    /** @test */
    public function a_day_off_carries_no_location()
    {
        // Only Saturday is a working day, and today is Sunday.
        $employee = $this->employeeWithDays([
            ['weekday' => 6, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        $row = Attendance::first();

        $this->assertSame(Attendance::STATUS_DAY_OFF, $row->status);
        $this->assertNull($row->location, 'Nothing was expected, so there is no place it was expected at.');
    }

    // ---------------------------------------------------------------------
    // Open-ended days
    // ---------------------------------------------------------------------

    /** @test */
    public function a_day_with_no_end_time_expects_no_fixed_hours()
    {
        $day = WorkScheduleDay::create([
            'work_schedule_id' => WorkSchedule::create(['name' => 'Open'])->id,
            'weekday' => 0,
            'location' => 'office',
            'start_time' => '10:00',
            'end_time' => null,
        ]);

        $this->assertTrue($day->isOpenEnded());
        $this->assertSame(0, $day->fresh()->expected_minutes);
    }

    /** @test */
    public function an_open_ended_day_still_records_the_hours_actually_worked()
    {
        // Nawal's Sunday: starts at 10:00, leaves when the work is done.
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'office', 'start_time' => '10:00', 'end_time' => null],
        ], 'Nawal');

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        // Already Sunday 10:00 Damascus from setUp.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(201)
            ->assertJsonPath('data.schedule.is_open_ended', true)
            ->assertJsonPath('data.schedule.end', null)
            ->assertJsonPath('data.schedule.expected_minutes', 0);

        CarbonImmutable::setTestNow('2026-09-20 11:30:00'); // 14:30 Damascus

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])
            ->assertOk()
            ->assertJsonPath('data.attendance.worked_minutes', 270)
            ->assertJsonPath('data.attendance.status', Attendance::STATUS_PRESENT);
    }

    /** @test */
    public function an_open_ended_schedule_can_be_created_through_the_api()
    {
        $this->asAdmin()
            ->postJson('/api/v1/work-schedules', [
                'name' => 'Sunday open',
                'days' => [
                    ['weekday' => 0, 'location' => 'office', 'start_time' => '10:00', 'end_time' => null],
                    ['weekday' => 1, 'location' => 'remote', 'start_time' => '08:00', 'end_time' => '13:00'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.days.0.is_open_ended', true)
            ->assertJsonPath('data.days.0.end_time', null)
            ->assertJsonPath('data.days.0.location', 'office')
            ->assertJsonPath('data.days.1.location', 'remote')
            // Only the closed day contributes expected hours.
            ->assertJsonPath('data.weekly_minutes', 300);
    }

    // ---------------------------------------------------------------------
    // Employees outside the module
    // ---------------------------------------------------------------------

    /** @test */
    public function an_untracked_employee_cannot_check_in_and_is_not_offered_the_button()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 6, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);
        $employee->update(['tracks_attendance' => false]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.can_check_in', false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(409)
            ->assertJson(['error_code' => 'ATTENDANCE_NOT_TRACKED']);
    }

    /** @test */
    public function untracked_employees_are_absent_from_the_management_grid()
    {
        $tracked = $this->employeeWithDays([
            ['weekday' => 6, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ], 'Tracked');

        $untracked = Employee::factory()->create(['name' => 'Sales Person', 'department' => 'sales']);
        $untracked->update(['tracks_attendance' => false]);

        $response = $this->asAdmin()->getJson('/api/v1/attendance')->assertOk();

        $names = collect($response->json('data.records'))->pluck('employee_name');

        $this->assertContains('Tracked', $names->all());
        $this->assertNotContains('Sales Person', $names->all());

        // And they do not inflate the "needs a schedule" prompt.
        $this->asAdmin()
            ->getJson('/api/v1/attendance/overview')
            ->assertJsonPath('data.total_employees', 1)
            ->assertJsonPath('data.without_schedule', 0);

        $this->assertNotNull($tracked->id);
    }

    /** @test */
    public function the_nightly_close_skips_untracked_employees()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 6, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);
        $user = User::factory()->create();
        $employee->update(['user_id' => $user->id, 'tracks_attendance' => false]);

        // 2026-09-19 was a Saturday — a working day for this schedule, so an
        // absence would be recorded if the employee were tracked.
        $this->artisan('attendance:close-day', ['--date' => '2026-09-19'])->assertSuccessful();

        $this->assertSame(0, Attendance::count());
    }

    // ---------------------------------------------------------------------
    // The seeder itself
    // ---------------------------------------------------------------------

    /** @test */
    public function the_seeder_builds_the_real_schedules_and_excludes_sales_and_management()
    {
        Employee::factory()->create(['name' => 'حلا', 'department' => 'design']);
        Employee::factory()->create(['name' => 'سابين', 'department' => 'design']);
        Employee::factory()->create(['name' => 'سارة', 'department' => 'sales']);
        Employee::factory()->create(['name' => 'الإدارة', 'department' => 'management']);

        $this->seed(WorkScheduleSeeder::class);

        // حلا: Sunday–Thursday, office throughout.
        $hala = Employee::where('name', 'حلا')->first();
        $halaDays = $hala->currentSchedule->workSchedule->days;

        $this->assertTrue($hala->tracks_attendance);
        $this->assertSame([0, 1, 2, 3, 4], $halaDays->pluck('weekday')->sort()->values()->all());
        $this->assertSame(['office'], $halaDays->pluck('location')->unique()->all());

        // سابين: office on Sunday, remote Monday–Thursday.
        $sabine = Employee::where('name', 'سابين')->first();
        $sabineDays = $sabine->currentSchedule->workSchedule->days->keyBy('weekday');

        $this->assertSame('office', $sabineDays[0]->location);
        $this->assertSame('remote', $sabineDays[1]->location);
        $this->assertSame('remote', $sabineDays[4]->location);

        // Sales and management sit outside the module entirely.
        $this->assertFalse(Employee::where('name', 'سارة')->first()->tracks_attendance);
        $this->assertFalse(Employee::where('name', 'الإدارة')->first()->tracks_attendance);
        $this->assertNull(Employee::where('name', 'سارة')->first()->currentSchedule);
    }

    /** @test */
    public function the_seeder_is_re_runnable_without_stacking_assignments()
    {
        Employee::factory()->create(['name' => 'حلا', 'department' => 'design']);

        $this->seed(WorkScheduleSeeder::class);
        $this->seed(WorkScheduleSeeder::class);

        $this->assertSame(1, Employee::where('name', 'حلا')->first()->schedules()->count());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Working somewhere else, just for today
    // ---------------------------------------------------------------------

    /** @test */
    public function an_employee_can_check_in_from_home_on_an_office_day()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in', ['location' => 'remote'])
            ->assertStatus(201)
            ->assertJsonPath('data.attendance.location', 'remote')
            // What the week expected is kept beside it, so the day still reads
            // as an exception once the template moves on.
            ->assertJsonPath('data.attendance.scheduled_location', 'office')
            ->assertJsonPath('data.attendance.is_location_exception', true);

        $this->assertTrue(Attendance::first()->isLocationException());
    }

    /** @test */
    public function checking_in_without_naming_a_place_uses_the_schedule_and_is_no_exception()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(201)
            ->assertJsonPath('data.attendance.location', 'office')
            ->assertJsonPath('data.attendance.is_location_exception', false);
    }

    /** @test */
    public function working_from_home_changes_nothing_that_is_owed()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in', ['location' => 'remote'])
            ->assertStatus(201);

        $row = Attendance::first();

        // The place is the only thing that moved: the hours expected of the
        // employee and the status of the day are untouched by it.
        $this->assertSame(300, $row->expected_minutes);
        $this->assertSame(Attendance::STATUS_PRESENT, $row->status);
        $this->assertSame('08:00', substr((string) $row->scheduled_start, 0, 5));
    }

    /** @test */
    public function a_place_outside_the_two_known_ones_is_refused()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in', ['location' => 'the beach'])
            ->assertStatus(422);
    }

    /** @test */
    public function management_can_correct_where_a_day_was_worked()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        $row = Attendance::first();

        $this->asAdmin()
            ->patchJson("/api/v1/attendance/{$row->id}", [
                'location' => 'remote',
                'reason' => 'Confirmed with the team lead that she worked from home.',
            ])
            ->assertOk()
            ->assertJsonPath('data.location', 'remote')
            // A correction moves where the day was worked, never what the week
            // expected — otherwise the exception would erase itself.
            ->assertJsonPath('data.scheduled_location', 'office')
            ->assertJsonPath('data.is_location_exception', true);
    }

    /** @test */
    public function editing_the_week_afterwards_does_not_erase_a_past_exception()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in', ['location' => 'remote'])
            ->assertStatus(201);

        // The employee is moved to permanently remote from now on.
        WorkScheduleDay::where('weekday', 0)->update(['location' => 'remote']);

        $row = Attendance::first()->fresh();

        // That day was still an exception when it happened.
        $this->assertSame('office', $row->scheduled_location);
        $this->assertTrue($row->isLocationException());
    }

    /** @test */
    public function an_evening_return_cannot_move_where_the_day_was_worked()
    {
        $employee = $this->employeeWithDays([
            ['weekday' => 0, 'location' => 'office', 'start_time' => '08:00', 'end_time' => '13:00'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('team');
        $employee->update(['user_id' => $user->id]);

        // The morning, in the office as scheduled.
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in')->assertStatus(201);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Client brochure.'])
            ->assertOk();

        // Back in the evening, from home. The day was still worked at the
        // office — one row, one place, decided when the day started.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in', ['location' => 'remote'])
            ->assertStatus(201)
            ->assertJsonPath('data.attendance.location', 'office')
            ->assertJsonPath('data.attendance.is_location_exception', false);

        $this->assertSame(2, Attendance::first()->sessions()->count());
    }
}
