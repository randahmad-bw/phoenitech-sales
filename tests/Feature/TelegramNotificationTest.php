<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Telegram mirror.
 *
 * Two rules are worth more here than the wording of any message:
 *
 * 1. **Nothing leaves the system unless it was configured to.** An install
 *    with no bot — every test run, every fresh clone — must never open a
 *    connection to api.telegram.org.
 * 2. **Telegram can fail and attendance still stands.** A refused, timed-out
 *    or unreachable Telegram is a log line, never a failed check-in.
 */
class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['name' => 'Rand & Co']);
        $this->user->assignRole('team');
        $this->employee = Employee::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Rand & Co',
        ]);

        $this->manager = User::factory()->create();
        $this->manager->assignRole('general_manager');

        $schedule = WorkSchedule::create(['name' => 'Office']);
        foreach ([0, 1, 2, 3, 4] as $weekday) {
            $schedule->days()->create([
                'weekday' => $weekday, 'location' => 'office',
                'start_time' => '09:00:00', 'end_time' => '17:00:00', 'break_minutes' => 60,
            ]);
        }
        EmployeeSchedule::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        // A Sunday, 09:05 in Asia/Damascus — the timezone seam the message has
        // to cross, since the row itself is stored in UTC.
        CarbonImmutable::setTestNow('2026-09-20 06:05:00');
    }

    private function configureBot(bool $enabled = true): void
    {
        config([
            'telegram.enabled' => $enabled,
            'telegram.token' => 'test-token',
            'telegram.chat_id' => '-1001234567890',
            'telegram.queue' => false,
        ]);
    }

    /** The text of the one message that was sent. */
    private function sentMessage(): string
    {
        $sent = null;

        Http::assertSent(function (Request $request) use (&$sent) {
            $sent = $request['text'];

            return str_contains($request->url(), '/sendMessage');
        });

        return (string) $sent;
    }

    // ---------------------------------------------------------------------
    // Nothing leaves an unconfigured install
    // ---------------------------------------------------------------------

    /** @test */
    public function an_install_without_a_bot_never_contacts_telegram()
    {
        Http::fake();

        // No configureBot() call: this is the default state of the app.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertCreated();

        Http::assertNothingSent();
    }

    /** @test */
    public function a_configured_bot_that_is_switched_off_sends_nothing()
    {
        Http::fake();
        $this->configureBot(enabled: false);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertCreated();

        Http::assertNothingSent();
    }

    /** @test */
    public function an_event_can_be_switched_off_on_its_own()
    {
        Http::fake();
        $this->configureBot();
        config(['telegram.events.attendance.check_in' => false]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertCreated();

        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------------
    // What the group receives
    // ---------------------------------------------------------------------

    /** @test */
    public function a_check_in_is_announced_in_the_company_timezone()
    {
        Http::fake();
        $this->configureBot();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertCreated();

        $message = $this->sentMessage();

        // 06:05 UTC is 09:05 in Damascus — the hour a manager recognises.
        $this->assertStringContainsString('09:05', $message);
        $this->assertStringContainsString('(الدوام 09:00)', $message);
        $this->assertStringContainsString('تسجيل حضور', $message);
        $this->assertStringContainsString('المكتب', $message);
        $this->assertStringContainsString('2026-09-20', $message);

        // The message is parsed as HTML, so a name with an ampersand in it
        // must arrive escaped or Telegram drops the whole message.
        $this->assertStringContainsString('Rand &amp; Co', $message);
    }

    /** @test */
    public function a_check_out_reports_the_hours_worked()
    {
        Http::fake();
        $this->configureBot();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertCreated();

        // Four hours later, minus the scheduled one-hour break.
        CarbonImmutable::setTestNow('2026-09-20 10:05:00');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/attendance/check-out', ['notes' => 'Closed the October proposal.'])
            ->assertOk();

        /*
         * The messages are asserted by content, not by count. `terminate()`
         * replays the whole list of terminating callbacks, and a test issues
         * several requests into one application — so the check-in message is
         * sent again when the check-out request terminates. A real request is
         * one process and sends each message once; this is an artefact of the
         * test harness, not something to design around.
         */
        $messages = collect(Http::recorded())->map(fn (array $pair) => $pair[0]['text']);

        $this->assertTrue($messages->contains(fn (string $text) => str_contains($text, 'تسجيل انصراف')
            && str_contains($text, '13:05')
            && str_contains($text, '3:00')));
    }

    /** @test */
    public function a_leave_request_reaches_the_group()
    {
        Http::fake();
        $this->configureBot();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/leaves/my', [
                'leave_type' => 'annual',
                'start_date' => '2026-10-04',
                'end_date' => '2026-10-08',
                'reason' => 'Family trip.',
            ])
            ->assertCreated();

        $message = $this->sentMessage();

        $this->assertStringContainsString('طلب إجازة جديد', $message);
        $this->assertStringContainsString('2026-10-04', $message);
        $this->assertStringContainsString('2026-10-08', $message);
        $this->assertStringContainsString('Family trip.', $message);
    }

    // ---------------------------------------------------------------------
    // Telegram is never allowed to break the thing it reports on
    // ---------------------------------------------------------------------

    /** @test */
    public function a_refused_message_does_not_fail_the_check_in()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);
        $this->configureBot();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertCreated();

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-20 00:00:00',
        ]);
    }

    /** @test */
    public function an_unreachable_telegram_does_not_fail_the_check_in()
    {
        // What a blocked network actually looks like from inside the app.
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));
        $this->configureBot();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/attendance/check-in')
            ->assertCreated();

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-20 00:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
