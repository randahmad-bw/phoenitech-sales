<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A day can be worked in more than one sitting.
 *
 * The scenario management described: check in, work the day, check out — then
 * an urgent task arrives in the evening and the employee comes back. That
 * return is recorded with its own start and end, and it is what counts as
 * extra work. Running late inside the normal day does not.
 */
class AttendanceOvertimeTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('team');
        $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('general_manager');

        // Monday 2026-09-21, 08:00-13:00 office.
        $schedule = WorkSchedule::create(['name' => 'Office']);
        foreach ([0, 1, 2, 3, 4, 5, 6] as $weekday) {
            $schedule->days()->create([
                'weekday' => $weekday, 'location' => 'office',
                'start_time' => '08:00', 'end_time' => '13:00', 'break_minutes' => 0,
            ]);
        }
        EmployeeSchedule::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);
    }

    private function asEmployee(): self
    {
        $this->actingAs($this->user, 'sanctum');

        return $this;
    }

    private function asAdmin(): self
    {
        $this->actingAs($this->admin, 'sanctum');

        return $this;
    }

    /** Damascus is UTC+3, so 05:00 UTC is 08:00 local. */
    private function atLocal(string $time): void
    {
        CarbonImmutable::setTestNow("2026-09-21 {$time}");
    }

    // ---------------------------------------------------------------------
    // The scenario
    // ---------------------------------------------------------------------

    /** @test */
    public function an_employee_who_returns_in_the_evening_records_a_second_session()
    {
        $this->atLocal('05:00'); // 08:00 local — start of the working day
        $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        $this->atLocal('10:00'); // 13:00 local — day finished
        $this->asEmployee()
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])
            ->assertOk()
            ->assertJsonPath('data.state', 'completed')
            // The button comes back, and the card says what it would mean.
            ->assertJsonPath('data.can_check_in', true)
            ->assertJsonPath('data.next_is_overtime', true);

        $this->atLocal('17:00'); // 20:00 local — urgent task
        $this->asEmployee()
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(201)
            ->assertJsonPath('data.state', 'working')
            ->assertJsonPath('data.attendance.sessions_count', 2);

        $this->atLocal('19:00'); // 22:00 local
        $response = $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();

        $sessions = collect($response->json('data.attendance.sessions'));

        $this->assertCount(2, $sessions);

        // Session 1: the working day.
        $this->assertSame('08:00', $sessions[0]['check_in_time']);
        $this->assertSame('13:00', $sessions[0]['check_out_time']);
        $this->assertSame(300, $sessions[0]['worked_minutes']);
        $this->assertFalse($sessions[0]['is_overtime']);
        $this->assertNull($sessions[0]['overtime_status']);

        // Session 2: the return — extra work, awaiting a decision.
        $this->assertSame('20:00', $sessions[1]['check_in_time']);
        $this->assertSame('22:00', $sessions[1]['check_out_time']);
        $this->assertSame(120, $sessions[1]['worked_minutes']);
        $this->assertTrue($sessions[1]['is_overtime']);
        $this->assertSame(AttendanceSession::OVERTIME_PENDING, $sessions[1]['overtime_status']);

        // The day's summary spans both, and totals both.
        $response
            ->assertJsonPath('data.attendance.check_in_time', '08:00')
            ->assertJsonPath('data.attendance.check_out_time', '22:00')
            ->assertJsonPath('data.attendance.worked_minutes', 420)
            ->assertJsonPath('data.attendance.overtime_minutes', 120)
            // Nothing is owed until someone says so.
            ->assertJsonPath('data.attendance.approved_overtime_minutes', 0);
    }

    /** @test */
    public function staying_late_inside_the_normal_day_is_not_overtime()
    {
        // The distinction management asked for: a shift that runs long is not
        // extra work. Only a deliberate return is.
        $this->atLocal('05:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        $this->atLocal('12:00'); // 15:00 local — two hours past the schedule
        $response = $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();

        $this->assertSame(420, $response->json('data.attendance.worked_minutes'));
        $this->assertSame(0, $response->json('data.attendance.overtime_minutes'));
        $this->assertFalse($response->json('data.attendance.sessions.0.is_overtime'));
    }

    /** @test */
    public function checking_in_twice_without_checking_out_is_still_refused()
    {
        $this->atLocal('05:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        $this->asEmployee()
            ->postJson('/api/v1/attendance/check-in')
            ->assertStatus(409)
            ->assertJson(['error_code' => 'ALREADY_CHECKED_IN']);

        $this->assertSame(1, AttendanceSession::count());
    }

    /** @test */
    public function a_third_session_is_also_extra_work()
    {
        foreach ([['05:00', '10:00'], ['11:00', '12:00'], ['17:00', '19:00']] as [$in, $out]) {
            $this->atLocal($in);
            $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertSuccessful();
            $this->atLocal($out);
            $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();
        }

        $attendance = Attendance::with('sessions')->first();

        $this->assertSame(3, $attendance->sessions->count());
        $this->assertFalse($attendance->sessions[0]->is_overtime);
        $this->assertTrue($attendance->sessions[1]->is_overtime);
        $this->assertTrue($attendance->sessions[2]->is_overtime);
        // 5h + 1h + 2h of extra work.
        $this->assertSame(180, $attendance->overtimeMinutes());
    }

    /** @test */
    public function the_scheduled_break_is_deducted_once_not_from_every_session()
    {
        // Someone who returns in the evening must not lose a lunch hour they
        // already took.
        $this->employee->currentSchedule->workSchedule->days()->update(['break_minutes' => 60]);

        $this->atLocal('05:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);
        $this->atLocal('10:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();

        $this->atLocal('17:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);
        $this->atLocal('19:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();

        $attendance = Attendance::with('sessions')->first();

        $this->assertSame(240, $attendance->sessions[0]->worked_minutes, '5h less the 1h break.');
        $this->assertSame(120, $attendance->sessions[1]->worked_minutes, 'The evening keeps its full 2h.');
        $this->assertSame(360, $attendance->worked_minutes);
    }

    // ---------------------------------------------------------------------
    // Review
    // ---------------------------------------------------------------------

    /** @test */
    public function management_approves_extra_work_and_it_becomes_owed()
    {
        $session = $this->recordOvertimeSession();

        $this->asAdmin()
            ->getJson('/api/v1/attendance/overtime')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.worked_minutes', 120);

        $this->asAdmin()
            ->patchJson("/api/v1/attendance/sessions/{$session->id}/overtime", [
                'status' => AttendanceSession::OVERTIME_APPROVED,
            ])
            ->assertOk()
            ->assertJsonPath('data.overtime_status', AttendanceSession::OVERTIME_APPROVED);

        $attendance = Attendance::with('sessions')->first();

        $this->assertSame(120, $attendance->approvedOvertimeMinutes());
        $this->assertSame($this->admin->id, $session->fresh()->reviewed_by);
        $this->assertNotNull($session->fresh()->reviewed_at);

        // Once decided, it leaves the queue.
        $this->asAdmin()->getJson('/api/v1/attendance/overtime')->assertJsonCount(0, 'data');
    }

    /** @test */
    public function rejecting_extra_work_requires_a_note()
    {
        $session = $this->recordOvertimeSession();

        $this->asAdmin()
            ->patchJson("/api/v1/attendance/sessions/{$session->id}/overtime", [
                'status' => AttendanceSession::OVERTIME_REJECTED,
            ])
            ->assertStatus(422);

        $this->asAdmin()
            ->patchJson("/api/v1/attendance/sessions/{$session->id}/overtime", [
                'status' => AttendanceSession::OVERTIME_REJECTED,
                'review_note' => 'Not requested by anyone.',
            ])
            ->assertOk()
            ->assertJsonPath('data.overtime_status', AttendanceSession::OVERTIME_REJECTED);

        $this->assertSame(0, Attendance::with('sessions')->first()->approvedOvertimeMinutes());
    }

    /** @test */
    public function an_ordinary_session_cannot_be_approved()
    {
        $this->atLocal('05:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);
        $this->atLocal('10:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();

        $session = AttendanceSession::first();

        $this->asAdmin()
            ->patchJson("/api/v1/attendance/sessions/{$session->id}/overtime", [
                'status' => AttendanceSession::OVERTIME_APPROVED,
            ])
            ->assertStatus(409)
            ->assertJson(['error_code' => 'NOT_OVERTIME']);
    }

    /** @test */
    public function extra_work_still_running_cannot_be_reviewed()
    {
        $this->atLocal('05:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);
        $this->atLocal('10:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.'])->assertOk();
        $this->atLocal('17:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        $session = AttendanceSession::where('sequence', 2)->first();

        $this->asAdmin()
            ->patchJson("/api/v1/attendance/sessions/{$session->id}/overtime", [
                'status' => AttendanceSession::OVERTIME_APPROVED,
            ])
            ->assertStatus(409)
            ->assertJson(['error_code' => 'OVERTIME_IN_PROGRESS']);
    }

    /** @test */
    public function an_employee_cannot_approve_their_own_extra_work()
    {
        $session = $this->recordOvertimeSession();

        $this->asEmployee()
            ->patchJson("/api/v1/attendance/sessions/{$session->id}/overtime", [
                'status' => AttendanceSession::OVERTIME_APPROVED,
            ])
            ->assertStatus(403);

        $this->asEmployee()->getJson('/api/v1/attendance/overtime')->assertStatus(403);
    }

    /**
     * Work the day, finish it, then come back for two hours.
     */
    private function recordOvertimeSession(): AttendanceSession
    {
        $this->atLocal('05:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in');
        $this->atLocal('10:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.']);
        $this->atLocal('17:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-in');
        $this->atLocal('19:00');
        $this->asEmployee()->postJson('/api/v1/attendance/check-out', ['notes' => 'Finished the landing page mock-ups.']);

        return AttendanceSession::where('sequence', 2)->firstOrFail();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
