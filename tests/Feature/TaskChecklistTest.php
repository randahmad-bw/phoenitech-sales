<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Task;
use App\Models\TaskItem;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lines inside a task, and closing the day by ticking them.
 *
 * Two lines these tests defend:
 *
 * 1. **The task follows its lines.** A task with a checklist is not something
 *    anybody has to remember to close: the last tick closes it, un-ticking
 *    reopens it, and the timestamps are the same ones the button writes.
 * 2. **A line you were given is not a line you can drop.** An assignee adds
 *    their own lines and ticks anything, but cannot delete what management
 *    put on the list — otherwise the checklist reports whatever is convenient.
 */
class TaskChecklistTest extends TestCase
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

    private function taskForWorker(array $attributes = []): Task
    {
        return Task::factory()->create($attributes + [
            'assigned_to' => $this->workerEmployee->id,
            'created_by' => $this->manager->id,
        ]);
    }

    // ─── Writing the list ───

    /** @test */
    public function a_task_is_created_with_its_lines_in_one_go()
    {
        $response = $this->asManager()->postJson('/api/v1/tasks', [
            'title' => 'Ramadan campaign',
            'assigned_to' => $this->workerEmployee->id,
            'items' => ['Three Instagram posts', '', 'Facebook cover', 'Animated story'],
        ]);

        // The blank row the form leaves behind is dropped, not refused.
        $response->assertCreated()->assertJsonCount(3, 'data.items');

        $this->assertSame(
            ['Three Instagram posts', 'Facebook cover', 'Animated story'],
            Task::latest('id')->first()->items->pluck('title')->all()
        );

        // Written in the order they were typed, whatever the ids end up being.
        $this->assertSame([1, 2, 3], Task::latest('id')->first()->items->pluck('position')->all());
    }

    /** @test */
    public function the_listing_carries_the_progress_without_shipping_every_line()
    {
        $task = $this->taskForWorker();
        TaskItem::factory()->done()->create(['task_id' => $task->id]);
        TaskItem::factory()->count(2)->create(['task_id' => $task->id]);

        $this->asWorker()->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('data.0.items_total', 3)
            ->assertJsonPath('data.0.items_done', 1)
            ->assertJsonMissingPath('data.0.items');
    }

    /** @test */
    public function the_assignee_adds_a_line_of_their_own()
    {
        $task = $this->taskForWorker();

        $this->asWorker()
            ->postJson("/api/v1/tasks/{$task->id}/items", ['title' => 'Helped Rami with the deck'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Helped Rami with the deck')
            ->assertJsonPath('data.is_done', false)
            ->assertJsonPath('data.is_own', true);

        $this->assertDatabaseHas('task_items', [
            'task_id' => $task->id,
            'created_by' => $this->worker->id,
        ]);
    }

    /** @test */
    public function an_employee_cannot_add_a_line_to_someone_elses_task()
    {
        $task = Task::factory()->create(['assigned_to' => Employee::factory()->create()->id]);

        $this->asWorker()
            ->postJson("/api/v1/tasks/{$task->id}/items", ['title' => 'Not mine'])
            ->assertForbidden();
    }

    /** @test */
    public function a_line_cannot_be_added_to_a_closed_task()
    {
        $task = $this->taskForWorker(['status' => Task::STATUS_DONE, 'completed_at' => now()]);

        $this->asManager()
            ->postJson("/api/v1/tasks/{$task->id}/items", ['title' => 'One more thing'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'TASK_CLOSED');
    }

    // ─── The task follows its lines ───

    /** @test */
    public function the_first_tick_starts_the_task_and_the_last_one_finishes_it()
    {
        $task = $this->taskForWorker();
        [$first, $second] = [
            TaskItem::factory()->create(['task_id' => $task->id, 'position' => 1]),
            TaskItem::factory()->create(['task_id' => $task->id, 'position' => 2]),
        ];

        $this->asWorker()
            ->patchJson("/api/v1/tasks/{$task->id}/items/{$first->id}", ['done' => true])
            ->assertOk()
            ->assertJsonPath('data.is_done', true);

        $task->refresh();
        $this->assertSame(Task::STATUS_IN_PROGRESS, $task->status);
        $this->assertNotNull($task->started_at);
        $this->assertNull($task->completed_at);

        $this->asWorker()
            ->patchJson("/api/v1/tasks/{$task->id}/items/{$second->id}", ['done' => true])
            ->assertOk();

        $task->refresh();
        $this->assertSame(Task::STATUS_DONE, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    /** @test */
    public function un_ticking_a_line_reopens_a_finished_task()
    {
        $task = $this->taskForWorker(['status' => Task::STATUS_DONE, 'completed_at' => now()]);
        $item = TaskItem::factory()->done()->create(['task_id' => $task->id]);

        $this->asWorker()
            ->patchJson("/api/v1/tasks/{$task->id}/items/{$item->id}", ['done' => false])
            ->assertOk()
            ->assertJsonPath('data.is_done', false)
            ->assertJsonPath('data.completed_at', null);

        $task->refresh();
        $this->assertSame(Task::STATUS_IN_PROGRESS, $task->status);
        // A reopened task carries no finish time for work that is still running.
        $this->assertNull($task->completed_at);
    }

    /** @test */
    public function ticking_a_line_twice_does_not_move_the_time_on_it()
    {
        $task = $this->taskForWorker();
        $item = TaskItem::factory()->create(['task_id' => $task->id]);

        $this->asWorker()->patchJson("/api/v1/tasks/{$task->id}/items/{$item->id}", ['done' => true]);
        $first = $item->refresh()->completed_at;

        CarbonImmutable::setTestNow('2026-09-22 15:00:00');
        $this->asWorker()->patchJson("/api/v1/tasks/{$task->id}/items/{$item->id}", ['done' => true])->assertOk();

        $this->assertTrue($first->equalTo($item->refresh()->completed_at));
    }

    /** @test */
    public function a_task_with_no_lines_is_left_alone()
    {
        $task = $this->taskForWorker(['status' => Task::STATUS_TODO]);
        $item = TaskItem::factory()->create(['task_id' => $task->id]);

        // Removing the only line empties the checklist; the task must not be
        // read as "all lines done" and closed.
        $this->asManager()->deleteJson("/api/v1/tasks/{$task->id}/items/{$item->id}")->assertOk();

        $this->assertSame(Task::STATUS_TODO, $task->refresh()->status);
    }

    /** @test */
    public function a_cancelled_task_does_not_come_back_because_of_a_checkbox()
    {
        $task = $this->taskForWorker(['status' => Task::STATUS_CANCELLED, 'completed_at' => now()]);
        $item = TaskItem::factory()->create(['task_id' => $task->id]);

        $this->asManager()->patchJson("/api/v1/tasks/{$task->id}/items/{$item->id}", ['done' => true])->assertOk();

        $this->assertSame(Task::STATUS_CANCELLED, $task->refresh()->status);
    }

    // ─── Removing a line ───

    /** @test */
    public function an_assignee_removes_their_own_line_but_not_one_they_were_given()
    {
        $task = $this->taskForWorker();
        $given = TaskItem::factory()->create(['task_id' => $task->id, 'created_by' => $this->manager->id]);
        $mine = TaskItem::factory()->create(['task_id' => $task->id, 'created_by' => $this->worker->id]);

        $this->asWorker()->deleteJson("/api/v1/tasks/{$task->id}/items/{$mine->id}")->assertOk();
        $this->assertDatabaseMissing('task_items', ['id' => $mine->id]);

        $this->asWorker()->deleteJson("/api/v1/tasks/{$task->id}/items/{$given->id}")->assertForbidden();
        $this->assertDatabaseHas('task_items', ['id' => $given->id]);
    }

    /** @test */
    public function management_removes_any_line()
    {
        $task = $this->taskForWorker();
        $item = TaskItem::factory()->create(['task_id' => $task->id, 'created_by' => $this->worker->id]);

        $this->asManager()->deleteJson("/api/v1/tasks/{$task->id}/items/{$item->id}")->assertOk();
        $this->assertDatabaseMissing('task_items', ['id' => $item->id]);
    }

    /** @test */
    public function a_line_cannot_be_reached_through_a_task_it_does_not_belong_to()
    {
        $mine = $this->taskForWorker();
        $other = $this->taskForWorker();
        $item = TaskItem::factory()->create(['task_id' => $other->id]);

        $this->asManager()
            ->patchJson("/api/v1/tasks/{$mine->id}/items/{$item->id}", ['done' => true])
            ->assertNotFound();
    }

    // ─── The check-out checklist ───

    /** @test */
    public function the_checklist_is_the_callers_own_open_lines()
    {
        $mine = $this->taskForWorker();
        TaskItem::factory()->create(['task_id' => $mine->id, 'title' => 'Still open']);
        TaskItem::factory()->done()->create(['task_id' => $mine->id, 'title' => 'Already done']);

        $closed = $this->taskForWorker(['status' => Task::STATUS_DONE, 'completed_at' => now()]);
        TaskItem::factory()->create(['task_id' => $closed->id, 'title' => 'On a closed task']);

        $theirs = Task::factory()->create(['assigned_to' => Employee::factory()->create()->id]);
        TaskItem::factory()->create(['task_id' => $theirs->id, 'title' => 'Somebody elses']);

        $this->asWorker()->getJson('/api/v1/tasks/checklist')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Still open')
            // The task is named, because the lines come from several at once.
            ->assertJsonPath('data.0.task_title', $mine->title);
    }

    /** @test */
    public function checking_out_ticks_the_lines_and_writes_the_day_from_them()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:05:00');

        $task = $this->taskForWorker();
        $one = TaskItem::factory()->create(['task_id' => $task->id, 'title' => 'Three Instagram posts', 'position' => 1]);
        $two = TaskItem::factory()->create(['task_id' => $task->id, 'title' => 'Facebook cover', 'position' => 2]);

        $this->asWorker()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        CarbonImmutable::setTestNow('2026-09-16 14:00:00');

        $this->asWorker()->postJson('/api/v1/attendance/check-out', [
            'completed_item_ids' => [$one->id],
            'notes' => 'Helped Rami with the deck.',
        ])->assertOk();

        $this->assertNotNull($one->refresh()->completed_at);
        $this->assertSame($this->worker->id, $one->completed_by);
        $this->assertNull($two->refresh()->completed_at);

        // The day reads as the lines plus whatever was typed beside them.
        $this->assertSame(
            "• Three Instagram posts\nHelped Rami with the deck.",
            $task->assignee->attendances()->latest('work_date')->first()->sessions()->first()->notes
        );

        // One line of two: the task started, and did not finish.
        $this->assertSame(Task::STATUS_IN_PROGRESS, $task->refresh()->status);
    }

    /** @test */
    public function a_day_with_nothing_ticked_still_has_to_say_what_happened()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:05:00');

        $this->asWorker()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        CarbonImmutable::setTestNow('2026-09-16 14:00:00');

        $this->asWorker()->postJson('/api/v1/attendance/check-out', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('notes');
    }

    /** @test */
    public function ticking_a_line_is_account_enough_on_its_own()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:05:00');

        $task = $this->taskForWorker();
        $item = TaskItem::factory()->create(['task_id' => $task->id, 'title' => 'Animated story']);

        $this->asWorker()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        CarbonImmutable::setTestNow('2026-09-16 14:00:00');

        $this->asWorker()
            ->postJson('/api/v1/attendance/check-out', ['completed_item_ids' => [$item->id]])
            ->assertOk();

        $this->assertSame(Task::STATUS_DONE, $task->refresh()->status);
    }

    /** @test */
    public function check_out_cannot_tick_another_persons_line()
    {
        $this->assignOfficeSchedule();
        CarbonImmutable::setTestNow('2026-09-16 06:05:00');

        $theirs = Task::factory()->create(['assigned_to' => Employee::factory()->create()->id]);
        $item = TaskItem::factory()->create(['task_id' => $theirs->id]);

        $this->asWorker()->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        CarbonImmutable::setTestNow('2026-09-16 14:00:00');

        // Accepted — with the note carrying the day — but the line is not theirs
        // to close, so it is simply not found and stays open.
        $this->asWorker()->postJson('/api/v1/attendance/check-out', [
            'completed_item_ids' => [$item->id],
            'notes' => 'Worked on my own things.',
        ])->assertOk();

        $this->assertNull($item->refresh()->completed_at);
    }

    /**
     * Sunday–Thursday, 09:00–17:00, one hour of break.
     */
    private function assignOfficeSchedule(): void
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

        EmployeeSchedule::create([
            'employee_id' => $this->workerEmployee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);
    }
}
