<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Working weeks are edited per employee, not per template.
 *
 * Templates still carry the data underneath — they hold the day rows and the
 * dated assignment that keeps history straight — but management never sees
 * one, and editing one person must never change another's week.
 */
class EmployeeWeekTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('general_manager');

        CarbonImmutable::setTestNow('2026-09-21 07:00:00'); // Monday
    }

    private function asAdmin(): self
    {
        $this->actingAs($this->admin, 'sanctum');

        return $this;
    }

    private function week(array $days): array
    {
        return ['days' => $days];
    }

    private function day(int $weekday, string $start = '08:00', ?string $end = '13:00', string $location = 'office'): array
    {
        return [
            'weekday' => $weekday,
            'location' => $location,
            'start_time' => $start,
            'end_time' => $end,
            'break_minutes' => 0,
        ];
    }

    // ---------------------------------------------------------------------
    // Listing
    // ---------------------------------------------------------------------

    /** @test */
    public function the_list_shows_every_employee_with_their_week()
    {
        $withWeek = Employee::factory()->create(['name' => 'Has Week', 'department' => 'design']);
        Employee::factory()->create(['name' => 'No Week', 'department' => 'design']);

        $this->asAdmin()->putJson("/api/v1/employees/{$withWeek->id}/week", $this->week([
            $this->day(0), $this->day(1, '08:00', '13:00', 'remote'),
        ]))->assertOk();

        $response = $this->asAdmin()->getJson('/api/v1/employee-weeks')->assertOk();
        $rows = collect($response->json('data'))->keyBy('name');

        $this->assertTrue($rows['Has Week']['has_schedule']);
        $this->assertSame(2, $rows['Has Week']['working_days_count']);
        $this->assertSame(600, $rows['Has Week']['weekly_minutes']);
        $this->assertSame('remote', $rows['Has Week']['days'][1]['location']);

        $this->assertFalse($rows['No Week']['has_schedule']);
        $this->assertSame([], $rows['No Week']['days']);
    }

    // ---------------------------------------------------------------------
    // Editing one person
    // ---------------------------------------------------------------------

    /** @test */
    public function a_week_can_be_set_with_per_day_hours_and_locations()
    {
        $employee = Employee::factory()->create();

        $this->asAdmin()
            ->putJson("/api/v1/employees/{$employee->id}/week", $this->week([
                $this->day(6, '08:00', '13:00', 'office'),
                $this->day(0, '08:00', '13:00', 'office'),
                $this->day(1, '08:00', '13:00', 'remote'),
                // An open-ended day: a start with no finish.
                $this->day(2, '10:00', null, 'office'),
            ]))
            ->assertOk()
            ->assertJsonPath('data.working_days_count', 4)
            // The open-ended day expects nothing, so only the three closed
            // days contribute: 3 × 5h.
            ->assertJsonPath('data.weekly_minutes', 900);

        $days = collect($this->asAdmin()->getJson('/api/v1/employee-weeks')->json('data.0.days'))->keyBy('weekday');

        $this->assertSame('remote', $days[1]['location']);
        $this->assertTrue($days[2]['is_open_ended']);
        $this->assertNull($days[2]['end_time']);
        $this->assertSame('10:00', $days[2]['start_time']);
    }

    /** @test */
    public function editing_one_employee_never_changes_a_colleague_who_shared_a_schedule()
    {
        // The seeder deliberately shares one template between people on the
        // same week. Editing either must split them, not move both.
        $shared = WorkSchedule::create(['name' => 'Shared week']);
        foreach ([0, 1, 2] as $weekday) {
            $shared->days()->create([
                'weekday' => $weekday, 'location' => 'office',
                'start_time' => '08:00', 'end_time' => '13:00', 'break_minutes' => 0,
            ]);
        }

        $alice = Employee::factory()->create(['name' => 'Alice']);
        $bob = Employee::factory()->create(['name' => 'Bob']);

        foreach ([$alice, $bob] as $employee) {
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $shared->id,
                'effective_from' => '2026-01-01',
            ]);
        }

        $this->asAdmin()
            ->putJson("/api/v1/employees/{$alice->id}/week", $this->week([$this->day(4, '14:00', '20:00', 'remote')]))
            ->assertOk();

        $rows = collect($this->asAdmin()->getJson('/api/v1/employee-weeks')->json('data'))->keyBy('name');

        $this->assertSame(1, $rows['Alice']['working_days_count']);
        $this->assertSame('remote', $rows['Alice']['days'][0]['location']);

        $this->assertSame(3, $rows['Bob']['working_days_count'], "Bob's week must be untouched.");
        $this->assertSame('office', $rows['Bob']['days'][0]['location']);
    }

    /** @test */
    public function a_second_edit_on_the_same_day_does_not_break_the_assignment()
    {
        // The first edit gives the employee their own schedule starting today;
        // a second edit must not try to close an assignment that began today.
        $employee = Employee::factory()->create();

        $this->asAdmin()->putJson("/api/v1/employees/{$employee->id}/week", $this->week([$this->day(0)]))->assertOk();
        $this->asAdmin()->putJson("/api/v1/employees/{$employee->id}/week", $this->week([$this->day(0), $this->day(1)]))->assertOk();
        $this->asAdmin()->putJson("/api/v1/employees/{$employee->id}/week", $this->week([$this->day(3)]))->assertOk();

        $this->assertSame(1, $employee->schedules()->whereNull('effective_to')->count());
        $this->assertSame(1, $this->asAdmin()->getJson('/api/v1/employee-weeks')->json('data.0.working_days_count'));
    }

    /** @test */
    public function an_empty_week_is_allowed_and_means_no_working_days()
    {
        $employee = Employee::factory()->create();

        $this->asAdmin()->putJson("/api/v1/employees/{$employee->id}/week", $this->week([$this->day(0)]))->assertOk();

        $this->asAdmin()
            ->putJson("/api/v1/employees/{$employee->id}/week", $this->week([]))
            ->assertOk()
            ->assertJsonPath('data.working_days_count', 0)
            ->assertJsonPath('data.has_schedule', false);
    }

    /** @test */
    public function editing_a_week_does_not_rewrite_what_is_already_recorded()
    {
        $employee = Employee::factory()->create();

        $this->asAdmin()->putJson("/api/v1/employees/{$employee->id}/week", $this->week([
            $this->day(0, '08:00', '13:00', 'office'),
        ]))->assertOk();

        $row = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => '2026-09-20',
            'scheduled_start' => '08:00:00',
            'scheduled_end' => '13:00:00',
            'location' => 'office',
            'expected_minutes' => 300,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->asAdmin()->putJson("/api/v1/employees/{$employee->id}/week", $this->week([
            $this->day(0, '14:00', '22:00', 'remote'),
        ]))->assertOk();

        $row->refresh();

        $this->assertSame('08:00:00', $row->scheduled_start);
        $this->assertSame('office', $row->location);
        $this->assertSame(300, $row->expected_minutes);
    }

    // ---------------------------------------------------------------------
    // Opting people out
    // ---------------------------------------------------------------------

    /** @test */
    public function an_employee_can_be_taken_off_the_attendance_system_from_the_same_screen()
    {
        $employee = Employee::factory()->create();

        $this->asAdmin()
            ->putJson("/api/v1/employees/{$employee->id}/week", [
                'days' => [],
                'tracks_attendance' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.tracks_attendance', false);

        $this->assertFalse($employee->fresh()->tracks_attendance);
    }

    /** @test */
    public function the_profile_endpoint_reports_whether_attendance_applies()
    {
        // The frontend hides the attendance screen from people it does not
        // apply to, and this is where it learns that.
        $user = User::factory()->create();
        $user->assignRole('team');
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.employee.tracks_attendance', true);

        $employee->update(['tracks_attendance' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.employee.tracks_attendance', false);
    }

    // ---------------------------------------------------------------------
    // Validation and authorization
    // ---------------------------------------------------------------------

    /** @test */
    public function a_weekday_cannot_appear_twice()
    {
        $employee = Employee::factory()->create();

        $this->asAdmin()
            ->putJson("/api/v1/employees/{$employee->id}/week", $this->week([$this->day(1), $this->day(1, '14:00')]))
            ->assertStatus(422);
    }

    /** @test */
    public function an_invalid_weekday_or_location_is_refused()
    {
        $employee = Employee::factory()->create();

        $this->asAdmin()
            ->putJson("/api/v1/employees/{$employee->id}/week", $this->week([$this->day(9)]))
            ->assertStatus(422);

        $this->asAdmin()
            ->putJson("/api/v1/employees/{$employee->id}/week", $this->week([$this->day(1, '08:00', '13:00', 'cafe')]))
            ->assertStatus(422);
    }

    /** @test */
    public function only_admins_may_set_working_weeks()
    {
        $employee = Employee::factory()->create();

        foreach (['sales_manager', 'marketing', 'sales', 'team'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/employee-weeks')
                ->assertStatus(403, "The {$role} role must not read working weeks.");

            $this->actingAs($user, 'sanctum')
                ->putJson("/api/v1/employees/{$employee->id}/week", $this->week([$this->day(1)]))
                ->assertStatus(403, "The {$role} role must not set working weeks.");
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
