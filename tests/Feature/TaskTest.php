<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Assigning work, and reporting on it.
 *
 * The line these tests defend: **assigning is management's, progress is the
 * worker's.** An employee can say they have started and finished their own
 * task and nothing else — not create work, not reassign it, not touch
 * anybody else's. Everything below is one way of getting that wrong.
 */
class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $worker;

    private Employee $workerEmployee;

    private Employee $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->assignRole('general_manager');

        $this->worker = User::factory()->create();
        $this->worker->assignRole('team');
        $this->workerEmployee = Employee::factory()->create(['user_id' => $this->worker->id]);

        $this->otherEmployee = Employee::factory()->create();

        CarbonImmutable::setTestNow('2026-09-22 09:00:00');
    }

    private function asManager(): self
    {
        $this->actingAs($this->manager, 'sanctum');

        return $this;
    }

    private function asWorker(): self
    {
        $this->actingAs($this->worker, 'sanctum');

        return $this;
    }

    // ─── Assigning ───

    /** @test */
    public function a_manager_assigns_a_task_to_an_employee()
    {
        $response = $this->asManager()->postJson('/api/v1/tasks', [
            'title' => 'Prepare the October proposal',
            'description' => 'Full scope and pricing.',
            'assigned_to' => $this->workerEmployee->id,
            'priority' => 'high',
            'due_date' => '2026-09-30',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Prepare the October proposal')
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.assignee_name', $this->workerEmployee->name)
            ->assertJsonPath('data.creator_name', $this->manager->name);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Prepare the October proposal',
            'assigned_to' => $this->workerEmployee->id,
            'created_by' => $this->manager->id,
            'status' => 'todo',
        ]);
    }

    /** @test */
    public function a_task_cannot_be_created_without_someone_responsible_for_it()
    {
        $this->asManager()
            ->postJson('/api/v1/tasks', ['title' => 'Something, by somebody'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assigned_to');
    }

    /** @test */
    public function a_task_always_starts_as_todo_whatever_the_client_sends()
    {
        // A task created as "done" records work that never happened, so the
        // field simply has nowhere to land.
        $this->asManager()->postJson('/api/v1/tasks', [
            'title' => 'Already finished, apparently',
            'assigned_to' => $this->workerEmployee->id,
            'status' => 'done',
            'completed_at' => '2026-09-01 10:00:00',
        ])->assertCreated()->assertJsonPath('data.status', 'todo');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Already finished, apparently',
            'status' => 'todo',
            'completed_at' => null,
        ]);
    }

    /** @test */
    public function an_employee_cannot_assign_work()
    {
        $this->asWorker()->postJson('/api/v1/tasks', [
            'title' => 'A task I gave myself',
            'assigned_to' => $this->workerEmployee->id,
        ])->assertForbidden();
    }

    // ─── Seeing ───

    /** @test */
    public function an_employee_sees_only_their_own_tasks()
    {
        Task::factory()->create(['assigned_to' => $this->workerEmployee->id, 'title' => 'Mine']);
        Task::factory()->create(['assigned_to' => $this->otherEmployee->id, 'title' => 'Not mine']);

        $response = $this->asWorker()->getJson('/api/v1/tasks');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mine');
    }

    /** @test */
    public function a_manager_sees_the_whole_companys_tasks()
    {
        Task::factory()->create(['assigned_to' => $this->workerEmployee->id]);
        Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

        $this->asManager()->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function an_employee_cannot_open_someone_elses_task()
    {
        $task = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

        $this->asWorker()->getJson("/api/v1/tasks/{$task->id}")->assertForbidden();
    }

    /** @test */
    public function the_listing_puts_overdue_work_first()
    {
        Task::factory()->create(['assigned_to' => $this->workerEmployee->id, 'title' => 'Later', 'due_date' => '2026-10-15']);
        Task::factory()->overdue()->create(['assigned_to' => $this->workerEmployee->id, 'title' => 'Late']);
        Task::factory()->done()->create(['assigned_to' => $this->workerEmployee->id, 'title' => 'Finished']);

        $this->asWorker()->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Late')
            ->assertJsonPath('data.0.is_overdue', true)
            ->assertJsonPath('data.2.title', 'Finished');
    }

    /** @test */
    public function the_summary_counts_only_what_the_caller_may_see()
    {
        Task::factory()->overdue()->create(['assigned_to' => $this->workerEmployee->id]);
        Task::factory()->create(['assigned_to' => $this->workerEmployee->id, 'due_date' => '2026-09-22']);
        Task::factory()->count(3)->create(['assigned_to' => $this->otherEmployee->id]);

        $this->asWorker()->getJson('/api/v1/tasks/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.overdue', 1)
            ->assertJsonPath('data.due_today', 1);

        $this->asManager()->getJson('/api/v1/tasks/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 5);
    }

    /** @test */
    public function the_waiting_strip_lists_only_the_callers_own_untouched_work()
    {
        Task::factory()->overdue()->create(['assigned_to' => $this->workerEmployee->id, 'title' => 'Late and untouched']);
        Task::factory()->create(['assigned_to' => $this->workerEmployee->id, 'title' => 'Waiting']);
        Task::factory()->create(['assigned_to' => $this->workerEmployee->id, 'title' => 'Already running', 'status' => Task::STATUS_IN_PROGRESS]);
        Task::factory()->done()->create(['assigned_to' => $this->workerEmployee->id, 'title' => 'Finished']);
        Task::factory()->count(3)->create(['assigned_to' => $this->otherEmployee->id]);

        $this->asWorker()->getJson('/api/v1/tasks/pending')
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonCount(2, 'data.tasks')
            // Most pressing first, the same order the board is read in.
            ->assertJsonPath('data.tasks.0.title', 'Late and untouched')
            ->assertJsonPath('data.tasks.1.title', 'Waiting');
    }

    /** @test */
    public function the_waiting_strip_is_the_callers_own_even_for_a_manager()
    {
        $managerEmployee = Employee::factory()->create(['user_id' => $this->manager->id]);

        Task::factory()->create(['assigned_to' => $managerEmployee->id, 'title' => 'Mine']);
        Task::factory()->count(4)->create(['assigned_to' => $this->workerEmployee->id]);

        // `tasks.view_all` widens the board, never the badge: a manager cannot
        // start somebody else's task, so it must not count towards theirs.
        $this->asManager()->getJson('/api/v1/tasks/pending')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.tasks.0.title', 'Mine');
    }

    /** @test */
    public function an_account_with_no_employee_profile_has_nothing_waiting()
    {
        Task::factory()->count(2)->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asManager()->getJson('/api/v1/tasks/pending')
            ->assertOk()
            ->assertJsonPath('data.count', 0)
            ->assertJsonCount(0, 'data.tasks');
    }

    // ─── Moving ───

    /** @test */
    public function an_employee_moves_their_own_task_and_the_clock_is_the_systems()
    {
        $task = Task::factory()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asWorker()
            ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.started_at', '2026-09-22T09:00:00.000000Z');

        CarbonImmutable::setTestNow('2026-09-23 15:00:00');

        $this->asWorker()
            ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'done', 'completion_note' => 'Sent to the client.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.completed_at', '2026-09-23T15:00:00.000000Z')
            // The note goes on the thread too, so the task reads as one story.
            ->assertJsonPath('data.comments.0.body', 'Sent to the client.');
    }

    /** @test */
    public function an_employee_cannot_move_someone_elses_task()
    {
        $task = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

        $this->asWorker()
            ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
    }

    /** @test */
    public function cancelling_a_task_requires_a_reason()
    {
        $task = Task::factory()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asManager()
            ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('completion_note');

        $this->asManager()
            ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'cancelled', 'completion_note' => 'Client withdrew.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    /** @test */
    public function reopening_a_finished_task_clears_its_completion()
    {
        $task = Task::factory()->done()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asManager()
            ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.completed_at', null);
    }

    /** @test */
    public function moving_a_task_to_the_state_it_is_already_in_is_refused()
    {
        $task = Task::factory()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asManager()
            ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'todo'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'TASK_STATUS_UNCHANGED');
    }

    // ─── Editing ───

    /** @test */
    public function a_manager_reassigns_a_task()
    {
        $task = Task::factory()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asManager()
            ->patchJson("/api/v1/tasks/{$task->id}", ['assigned_to' => $this->otherEmployee->id, 'priority' => 'urgent'])
            ->assertOk()
            ->assertJsonPath('data.assignee_name', $this->otherEmployee->name)
            ->assertJsonPath('data.priority', 'urgent');
    }

    /** @test */
    public function an_employee_cannot_reassign_or_redate_their_own_task()
    {
        // The one thing they may change about it is how far along it is.
        $task = Task::factory()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asWorker()
            ->patchJson("/api/v1/tasks/{$task->id}", ['due_date' => '2027-01-01'])
            ->assertForbidden();
    }

    /** @test */
    public function a_closed_task_is_reopened_before_it_is_changed()
    {
        $task = Task::factory()->done()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asManager()
            ->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed after the fact'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'TASK_CLOSED');
    }

    // ─── Following ───

    /** @test */
    public function anyone_who_can_see_a_task_can_add_to_its_thread()
    {
        $task = Task::factory()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asWorker()
            ->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Waiting on the client’s logo files.'])
            ->assertCreated()
            ->assertJsonPath('data.author_name', $this->worker->name);

        $this->asManager()
            ->getJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.comments');
    }

    /** @test */
    public function a_comment_on_someone_elses_task_is_refused()
    {
        $task = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

        $this->asWorker()
            ->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Butting in.'])
            ->assertForbidden();
    }

    // ─── Deleting ───

    /** @test */
    public function only_someone_with_delete_may_remove_a_task()
    {
        $task = Task::factory()->create(['assigned_to' => $this->workerEmployee->id]);

        $this->asWorker()->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();

        $this->asManager()->deleteJson("/api/v1/tasks/{$task->id}")->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
