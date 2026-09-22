<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\Notification;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requesting leave, and deciding on it.
 *
 * These are two acts by two people. Approved leave turns a day on the
 * attendance calendar from an absence into `leave`, so the ability to grant it
 * must never sit with the person it benefits — which is exactly what the old
 * endpoint allowed.
 */
class LeaveRequestTest extends TestCase
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

        // Sunday–Thursday, so Friday and Saturday are days off.
        $schedule = WorkSchedule::create(['name' => 'Office']);
        foreach ([0, 1, 2, 3, 4] as $weekday) {
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

        CarbonImmutable::setTestNow('2026-09-21 07:00:00');
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'leave_type' => 'annual',
            'start_date' => '2026-10-04', // Sunday
            'end_date' => '2026-10-08',   // Thursday
            'reason' => 'Family trip.',
        ], $overrides);
    }

    // ---------------------------------------------------------------------
    // The escalation this replaces
    // ---------------------------------------------------------------------

    /** @test */
    public function an_employee_cannot_approve_their_own_leave()
    {
        // The old endpoint accepted `status`, so posting `approved` granted it
        // outright — and erased the employee's own absences on the calendar.
        $this->asEmployee()
            ->postJson('/api/v1/leaves/my', $this->payload(['status' => EmployeeLeave::STATUS_APPROVED]))
            ->assertStatus(201)
            ->assertJsonPath('data.status', EmployeeLeave::STATUS_PENDING)
            ->assertJsonPath('data.decided_by', null);

        $this->assertSame(EmployeeLeave::STATUS_PENDING, EmployeeLeave::first()->status);
    }

    /** @test */
    public function an_employee_cannot_reach_the_review_endpoint()
    {
        $leave = $this->submitRequest();

        $this->asEmployee()
            ->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_APPROVED])
            ->assertStatus(403);

        $this->assertTrue(EmployeeLeave::first()->isPending());
    }

    /** @test */
    public function the_legacy_management_endpoint_is_no_longer_open_to_employees()
    {
        // It still accepts a status, so it is now a management tool.
        $this->asEmployee()
            ->postJson("/api/v1/employees/{$this->employee->id}/leaves", [
                'leave_type' => 'annual',
                'start_date' => '2026-10-04',
                'end_date' => '2026-10-08',
                'status' => EmployeeLeave::STATUS_APPROVED,
            ])
            ->assertStatus(403);

        $this->assertSame(0, EmployeeLeave::count());
    }

    // ---------------------------------------------------------------------
    // The flow
    // ---------------------------------------------------------------------

    /** @test */
    public function an_employee_submits_and_management_approves()
    {
        $this->asEmployee()
            ->postJson('/api/v1/leaves/my', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.status', EmployeeLeave::STATUS_PENDING);

        $leave = EmployeeLeave::first();

        $this->asAdmin()
            ->getJson('/api/v1/leaves?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asAdmin()
            ->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_APPROVED])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeLeave::STATUS_APPROVED)
            ->assertJsonPath('data.decided_by', $this->admin->name);

        $fresh = $leave->fresh();
        $this->assertSame($this->admin->id, $fresh->approved_by);
        $this->assertNotNull($fresh->decided_at);
    }

    /** @test */
    public function rejecting_requires_a_note()
    {
        $leave = $this->submitRequest();

        $this->asAdmin()
            ->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_REJECTED])
            ->assertStatus(422);

        $this->asAdmin()
            ->patchJson("/api/v1/leaves/{$leave->id}/review", [
                'status' => EmployeeLeave::STATUS_REJECTED,
                'decision_note' => 'Two others are already away that week.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeLeave::STATUS_REJECTED)
            ->assertJsonPath('data.decision_note', 'Two others are already away that week.');
    }

    /** @test */
    public function a_decided_request_cannot_be_decided_again()
    {
        $leave = $this->submitRequest();

        $this->asAdmin()->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_APPROVED])->assertOk();

        $this->asAdmin()
            ->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_REJECTED, 'decision_note' => 'changed my mind'])
            ->assertStatus(409)
            ->assertJson(['error_code' => 'LEAVE_ALREADY_DECIDED']);
    }

    // ---------------------------------------------------------------------
    // Day counting
    // ---------------------------------------------------------------------

    /** @test */
    public function leave_is_counted_in_working_days_not_calendar_days()
    {
        // Sunday 4th to Thursday 15th October spans 12 calendar days but only
        // 10 working days on a Sunday–Thursday week.
        $this->asEmployee()
            ->postJson('/api/v1/leaves/my', $this->payload(['start_date' => '2026-10-04', 'end_date' => '2026-10-15']))
            ->assertStatus(201)
            // Note: JSON carries this as 10, not 10.0 — json_encode drops a
            // zero fraction, and assertJsonPath compares strictly.
            ->assertJsonPath('data.days_count', 10);
    }

    /** @test */
    public function a_public_holiday_inside_the_range_is_not_spent()
    {
        Holiday::create(['date' => '2026-10-06', 'name' => 'Public holiday']);

        $this->asEmployee()
            ->postJson('/api/v1/leaves/my', $this->payload())
            ->assertStatus(201)
            // Sunday–Thursday is 5 days, less the holiday.
            ->assertJsonPath('data.days_count', 4);
    }

    /** @test */
    public function the_client_cannot_choose_the_day_count()
    {
        $this->asEmployee()
            ->postJson('/api/v1/leaves/my', $this->payload(['days_count' => 0.5]))
            ->assertStatus(201)
            ->assertJsonPath('data.days_count', 5);
    }

    // ---------------------------------------------------------------------
    // Overlaps, withdrawal, balance
    // ---------------------------------------------------------------------

    /** @test */
    public function overlapping_requests_are_refused()
    {
        $this->submitRequest();

        $this->asEmployee()
            ->postJson('/api/v1/leaves/my', $this->payload(['start_date' => '2026-10-07', 'end_date' => '2026-10-11']))
            ->assertStatus(409)
            ->assertJson(['error_code' => 'LEAVE_OVERLAP']);
    }

    /** @test */
    public function a_rejected_request_no_longer_blocks_the_dates()
    {
        $leave = $this->submitRequest();

        $this->asAdmin()->patchJson("/api/v1/leaves/{$leave->id}/review", [
            'status' => EmployeeLeave::STATUS_REJECTED,
            'decision_note' => 'Not this week.',
        ])->assertOk();

        $this->asEmployee()->postJson('/api/v1/leaves/my', $this->payload())->assertStatus(201);
    }

    /** @test */
    public function an_employee_may_withdraw_a_pending_request_but_not_a_decided_one()
    {
        $leave = $this->submitRequest();

        $this->asEmployee()->deleteJson("/api/v1/leaves/my/{$leave->id}")->assertOk();
        $this->assertSame(0, EmployeeLeave::count());

        $second = $this->submitRequest();
        $this->asAdmin()->patchJson("/api/v1/leaves/{$second->id}/review", ['status' => EmployeeLeave::STATUS_APPROVED]);

        $this->asEmployee()
            ->deleteJson("/api/v1/leaves/my/{$second->id}")
            ->assertStatus(409)
            ->assertJson(['error_code' => 'LEAVE_ALREADY_DECIDED']);
    }

    /** @test */
    public function an_employee_cannot_withdraw_someone_elses_request()
    {
        $other = Employee::factory()->create();
        $leave = EmployeeLeave::create([
            'employee_id' => $other->id,
            'leave_type' => 'annual',
            'start_date' => '2026-10-04',
            'end_date' => '2026-10-08',
            'days_count' => 5,
            'status' => EmployeeLeave::STATUS_PENDING,
        ]);

        $this->asEmployee()->deleteJson("/api/v1/leaves/my/{$leave->id}")->assertStatus(403);
        $this->assertSame(1, EmployeeLeave::count());
    }

    /** @test */
    public function the_balance_counts_approved_days_and_shows_pending_separately()
    {
        $leave = $this->submitRequest();

        $this->asEmployee()
            ->getJson('/api/v1/leaves/my')
            ->assertOk()
            ->assertJsonPath('data.balance.annual_used', 0)
            ->assertJsonPath('data.balance.annual_pending', 5)
            ->assertJsonPath('data.balance.annual_remaining', 21);

        $this->asAdmin()->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_APPROVED]);

        $this->asEmployee()
            ->getJson('/api/v1/leaves/my')
            ->assertJsonPath('data.balance.annual_used', 5)
            ->assertJsonPath('data.balance.annual_pending', 0)
            ->assertJsonPath('data.balance.annual_remaining', 16);
    }

    /** @test */
    public function an_employee_sees_only_their_own_requests()
    {
        $other = Employee::factory()->create();
        EmployeeLeave::create([
            'employee_id' => $other->id,
            'leave_type' => 'annual',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'days_count' => 5,
            'status' => EmployeeLeave::STATUS_PENDING,
        ]);
        $this->submitRequest();

        $this->asEmployee()->getJson('/api/v1/leaves/my')->assertOk()->assertJsonCount(1, 'data.requests');
        $this->asEmployee()->getJson('/api/v1/leaves')->assertStatus(403);
        $this->asAdmin()->getJson('/api/v1/leaves')->assertOk()->assertJsonCount(2, 'data');
    }

    // ---------------------------------------------------------------------
    // The point of it all
    // ---------------------------------------------------------------------

    /** @test */
    public function only_approved_leave_shows_on_the_attendance_calendar()
    {
        $leave = $this->submitRequest();

        // 2026-10-05 is a Monday inside the requested range.
        $this->asAdmin()
            ->getJson('/api/v1/attendance?date=2026-10-05')
            ->assertOk()
            ->assertJsonPath('data.records.0.status', Attendance::STATUS_PENDING);

        $this->asAdmin()->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_APPROVED]);

        $this->asAdmin()
            ->getJson('/api/v1/attendance?date=2026-10-05')
            ->assertJsonPath('data.records.0.status', Attendance::STATUS_LEAVE);
    }

    /** @test */
    public function an_approved_leave_day_is_never_recorded_as_an_absence()
    {
        $leave = $this->submitRequest();
        $this->asAdmin()->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_APPROVED]);

        CarbonImmutable::setTestNow('2026-10-06 07:00:00'); // the day after the 5th
        $this->artisan('attendance:close-day')->assertSuccessful();

        $this->assertSame(0, Attendance::where('status', Attendance::STATUS_ABSENT)->count());
    }

    /** @test */
    public function the_pending_count_reports_requests_awaiting_a_decision()
    {
        $this->asAdmin()
            ->getJson('/api/v1/leaves/pending-count')
            ->assertOk()
            ->assertJsonPath('data.pending', 0);

        $leave = $this->submitRequest();

        $this->asAdmin()
            ->getJson('/api/v1/leaves/pending-count')
            ->assertOk()
            ->assertJsonPath('data.pending', 1);

        // A decided request is no longer waiting on anyone.
        $this->asAdmin()->patchJson("/api/v1/leaves/{$leave->id}/review", ['status' => EmployeeLeave::STATUS_APPROVED]);

        $this->asAdmin()
            ->getJson('/api/v1/leaves/pending-count')
            ->assertJsonPath('data.pending', 0);
    }

    /** @test */
    public function an_employee_cannot_read_the_company_wide_pending_count()
    {
        $this->submitRequest();

        $this->asEmployee()
            ->getJson('/api/v1/leaves/pending-count')
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Telling management
    // ---------------------------------------------------------------------

    /** @test */
    public function filing_a_request_tells_everyone_who_can_decide_it()
    {
        $secondManager = User::factory()->create();
        $secondManager->assignRole('general_manager');

        $leave = $this->submitRequest();

        $notified = Notification::where('type', Notification::TYPE_LEAVE_REQUESTED)->get();

        $this->assertEqualsCanonicalizing(
            [$this->admin->id, $secondManager->id],
            $notified->pluck('user_id')->all(),
        );

        $notice = $notified->firstWhere('user_id', $this->admin->id);
        $this->assertSame($this->employee->name, $notice->data['actor']);
        $this->assertSame('2026-10-04', $notice->data['from']);
        $this->assertSame('2026-10-08', $notice->data['to']);
        $this->assertSame('/leaves?tab=inbox', $notice->link);
        $this->assertSame(EmployeeLeave::class, $notice->subject_type);
        $this->assertSame($leave->id, $notice->subject_id);
    }

    /** @test */
    public function nobody_without_a_say_in_it_is_told()
    {
        // Including the requester: the bell is for other people's actions.
        $colleague = User::factory()->create();
        $colleague->assignRole('team');

        $this->submitRequest();

        $this->assertSame(
            0,
            Notification::whereIn('user_id', [$this->user->id, $colleague->id])->count(),
        );
    }

    /** @test */
    public function a_deactivated_manager_is_not_notified()
    {
        $this->admin->update(['is_active' => false]);

        $this->submitRequest();

        $this->assertSame(0, Notification::where('user_id', $this->admin->id)->count());
    }

    /** @test */
    public function withdrawing_a_request_takes_its_notice_with_it()
    {
        $leave = $this->submitRequest();

        $this->assertSame(1, Notification::where('user_id', $this->admin->id)->count());

        $this->asEmployee()
            ->deleteJson("/api/v1/leaves/my/{$leave->id}")
            ->assertOk();

        $this->assertSame(0, Notification::where('user_id', $this->admin->id)->count());
    }

    private function submitRequest(): EmployeeLeave
    {
        $this->asEmployee()->postJson('/api/v1/leaves/my', $this->payload())->assertStatus(201);

        return EmployeeLeave::latest('id')->firstOrFail();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
