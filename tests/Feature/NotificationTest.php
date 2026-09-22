<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Being told what happened to your work.
 *
 * The line these tests defend: **a notification is about somebody else's
 * action, on your own work.** Nobody is told what they just did, nobody reads
 * anybody else's feed, and nothing is said twice — including by a reminder job
 * that runs more than once in a day.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $worker;

    private Employee $workerEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->assignRole('general_manager');

        $this->worker = User::factory()->create();
        $this->worker->assignRole('team');
        $this->workerEmployee = Employee::factory()->create(['user_id' => $this->worker->id]);

        CarbonImmutable::setTestNow('2026-09-22 09:00:00');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function assignTask(array $overrides = []): Task
    {
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/tasks', array_merge([
            'title' => 'Prepare the October proposal',
            'assigned_to' => $this->workerEmployee->id,
            'due_date' => '2026-09-30',
        ], $overrides))->assertCreated();

        return Task::latest('id')->first();
    }

    // ─── What gets announced ───

    /** @test */
    public function assigning_work_tells_the_person_it_landed_on()
    {
        $task = $this->assignTask();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->worker->id,
            'type' => Notification::TYPE_TASK_ASSIGNED,
            'subject_type' => Task::class,
            'subject_id' => $task->id,
        ]);

        $notification = Notification::forUser($this->worker->id)->first();
        $this->assertSame('Prepare the October proposal', $notification->data['task']);
        $this->assertSame($this->manager->name, $notification->data['actor']);
        $this->assertSame("/tasks?task={$task->id}", $notification->link);
        $this->assertNull($notification->read_at);
    }

    /** @test */
    public function nobody_is_notified_of_their_own_action()
    {
        // The manager assigned it, so the manager hears nothing about it.
        $this->assignTask();

        $this->assertSame(0, Notification::forUser($this->manager->id)->count());
    }

    /** @test */
    public function reassigning_tells_the_new_owner()
    {
        $task = $this->assignTask();
        $other = Employee::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/v1/tasks/{$task->id}", ['assigned_to' => $other->id])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $other->user_id,
            'type' => Notification::TYPE_TASK_ASSIGNED,
            'subject_id' => $task->id,
        ]);
    }

    /** @test */
    public function moving_a_task_tells_the_person_who_asked_for_it()
    {
        $task = $this->assignTask();

        $this->actingAs($this->worker, 'sanctum')
            ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertOk();

        $moved = Notification::forUser($this->manager->id)
            ->where('type', Notification::TYPE_TASK_STATUS)
            ->first();

        $this->assertNotNull($moved);
        $this->assertSame('done', $moved->data['status']);
        $this->assertSame($this->worker->name, $moved->data['actor']);
    }

    /** @test */
    public function closing_with_a_note_sends_one_notice_not_two()
    {
        // The note is written onto the thread as well, and a comment normally
        // announces itself — the completion notice already covers it.
        $task = $this->assignTask();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => 'cancelled',
                'completion_note' => 'The client withdrew the brief.',
            ])->assertOk();

        $this->assertSame(
            1,
            Notification::forUser($this->worker->id)->where('type', '!=', Notification::TYPE_TASK_ASSIGNED)->count()
        );
    }

    /** @test */
    public function a_comment_tells_the_other_side()
    {
        $task = $this->assignTask();

        $this->actingAs($this->worker, 'sanctum')
            ->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Started on the pricing section.'])
            ->assertCreated();

        $comment = Notification::forUser($this->manager->id)
            ->where('type', Notification::TYPE_TASK_COMMENT)
            ->first();

        $this->assertNotNull($comment);
        $this->assertStringContainsString('pricing section', $comment->data['excerpt']);

        // And the person who wrote it hears nothing.
        $this->assertSame(
            0,
            Notification::forUser($this->worker->id)->where('type', Notification::TYPE_TASK_COMMENT)->count()
        );
    }

    /** @test */
    public function an_employee_without_a_login_is_simply_not_notified()
    {
        $unlinked = Employee::factory()->create(['user_id' => null]);

        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/tasks', [
            'title' => 'Work for somebody with no account',
            'assigned_to' => $unlinked->id,
        ])->assertCreated();

        $this->assertSame(0, Notification::count());
    }

    // ─── Reading the feed ───

    /** @test */
    public function the_feed_only_ever_shows_your_own()
    {
        $this->assignTask();
        Notification::create([
            'user_id' => $this->manager->id,
            'type' => Notification::TYPE_TASK_COMMENT,
            'data' => ['task' => 'Not yours'],
        ]);

        $response = $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(Notification::TYPE_TASK_ASSIGNED, $response->json('data.0.type'));
    }

    /** @test */
    public function the_bell_counts_only_what_is_unread()
    {
        $this->assignTask();
        $this->assignTask(['title' => 'A second thing']);

        $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 2);

        $first = Notification::forUser($this->worker->id)->first();

        $this->actingAs($this->worker, 'sanctum')
            ->patchJson("/api/v1/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->actingAs($this->worker, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 1);
    }

    /** @test */
    public function clearing_the_bell_marks_everything_read()
    {
        $this->assignTask();
        $this->assignTask(['title' => 'A second thing']);

        $this->actingAs($this->worker, 'sanctum')
            ->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 2);

        $this->assertSame(0, Notification::forUser($this->worker->id)->unread()->count());
    }

    /** @test */
    public function somebody_elses_notification_cannot_be_marked_read()
    {
        $this->assignTask();
        $theirs = Notification::forUser($this->worker->id)->firstOrFail();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/v1/notifications/{$theirs->id}/read")
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at);
    }

    /** @test */
    public function a_deleted_task_takes_its_notices_with_it()
    {
        $task = $this->assignTask();

        $this->actingAs($this->manager, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$task->id}")
            ->assertOk();

        // The subject is gone; the notice must not survive pointing at nothing.
        $this->assertSame(
            0,
            Notification::where('subject_type', Task::class)->where('subject_id', $task->id)->count()
        );
    }

    // ─── The daily reminder ───

    /** @test */
    public function the_reminder_job_tells_owners_what_is_due_and_what_is_late()
    {
        Task::factory()->create([
            'assigned_to' => $this->workerEmployee->id,
            'created_by' => $this->manager->id,
            'due_date' => '2026-09-22',
        ]);
        Task::factory()->overdue()->create([
            'assigned_to' => $this->workerEmployee->id,
            'created_by' => $this->manager->id,
        ]);
        // Not due yet, and a finished one: neither is anybody's problem today.
        Task::factory()->create(['assigned_to' => $this->workerEmployee->id, 'due_date' => '2026-10-05']);
        Task::factory()->done()->create(['assigned_to' => $this->workerEmployee->id, 'due_date' => '2026-09-01']);

        $this->artisan('tasks:remind')->assertExitCode(0);

        $this->assertSame(
            1,
            Notification::forUser($this->worker->id)->where('type', Notification::TYPE_TASK_DUE_TODAY)->count()
        );
        $this->assertSame(
            1,
            Notification::forUser($this->worker->id)->where('type', Notification::TYPE_TASK_OVERDUE)->count()
        );
    }

    /** @test */
    public function running_the_reminder_twice_in_a_day_does_not_say_it_twice()
    {
        Task::factory()->overdue()->create([
            'assigned_to' => $this->workerEmployee->id,
            'created_by' => $this->manager->id,
        ]);

        $this->artisan('tasks:remind');
        $this->artisan('tasks:remind');

        $this->assertSame(1, Notification::forUser($this->worker->id)->count());
    }

    // ─── Access ───

    /** @test */
    public function the_feed_needs_authentication()
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }
}
