<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Files on a task.
 *
 * The line these tests defend: **attaching the work you were asked for is
 * doing the task, not editing it.** The assignee may put files on their own
 * task and take them off again; nobody may touch anybody else's; and a file
 * removed from the record is removed from the disk, not just from the list.
 */
class TaskAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $worker;

    private Employee $workerEmployee;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->assignRole('general_manager');

        $this->worker = User::factory()->create();
        $this->worker->assignRole('team');
        $this->workerEmployee = Employee::factory()->create(['user_id' => $this->worker->id]);

        $this->task = Task::factory()->create([
            'assigned_to' => $this->workerEmployee->id,
            'created_by' => $this->manager->id,
        ]);
    }

    private function upload(User $as, Task $task, ?UploadedFile $file = null)
    {
        return $this->actingAs($as, 'sanctum')->postJson(
            "/api/v1/tasks/{$task->id}/attachments",
            ['file' => $file ?? UploadedFile::fake()->create('brief.pdf', 64, 'application/pdf')]
        );
    }

    /** @test */
    public function the_assignee_attaches_a_file_to_their_own_task()
    {
        $this->upload($this->worker, $this->task)
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'brief.pdf');

        $attachment = Attachment::firstOrFail();

        // The class name, not the alias: this is what `$task->attachments`
        // looks for, and storing anything else makes the file invisible.
        $this->assertSame(Task::class, $attachment->attachable_type);
        $this->assertSame($this->task->id, (int) $attachment->attachable_id);
        Storage::disk('public')->assertExists($attachment->path);
    }

    /** @test */
    public function management_attaches_to_anybodys_task()
    {
        $this->upload($this->manager, $this->task)->assertCreated();

        $this->assertSame(1, $this->task->attachments()->count());
    }

    /** @test */
    public function an_employee_cannot_attach_to_someone_elses_task()
    {
        $someoneElse = Task::factory()->create(['created_by' => $this->manager->id]);

        $this->upload($this->worker, $someoneElse)->assertForbidden();

        $this->assertSame(0, Attachment::count());
    }

    /** @test */
    public function the_file_is_checked_before_it_is_stored()
    {
        $this->upload($this->worker, $this->task, UploadedFile::fake()->create('payload.exe', 10))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, Attachment::count());
    }

    /** @test */
    public function the_task_detail_lists_its_files()
    {
        $this->upload($this->worker, $this->task)->assertCreated();

        $this->actingAs($this->worker, 'sanctum')
            ->getJson("/api/v1/tasks/{$this->task->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.original_name', 'brief.pdf');
    }

    /** @test */
    public function removing_a_file_removes_it_from_the_disk_too()
    {
        $this->upload($this->worker, $this->task)->assertCreated();
        $attachment = Attachment::firstOrFail();

        $this->actingAs($this->worker, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$this->task->id}/attachments/{$attachment->id}")
            ->assertOk();

        $this->assertSame(0, Attachment::count());
        Storage::disk('public')->assertMissing($attachment->path);
    }

    /** @test */
    public function a_file_cannot_be_removed_through_a_task_it_does_not_belong_to()
    {
        $this->upload($this->worker, $this->task)->assertCreated();
        $attachment = Attachment::firstOrFail();

        $otherTask = Task::factory()->create([
            'assigned_to' => $this->workerEmployee->id,
            'created_by' => $this->manager->id,
        ]);

        $this->actingAs($this->worker, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$otherTask->id}/attachments/{$attachment->id}")
            ->assertNotFound();

        $this->assertSame(1, Attachment::count());
    }

    /** @test */
    public function deleting_a_task_takes_its_files_with_it()
    {
        $this->upload($this->worker, $this->task)->assertCreated();
        $path = Attachment::firstOrFail()->path;

        $this->actingAs($this->manager, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$this->task->id}")
            ->assertOk();

        $this->assertSame(0, Attachment::count());
        Storage::disk('public')->assertMissing($path);
    }

    /** @test */
    public function a_contract_upload_is_stored_where_the_contract_can_find_it()
    {
        // The regression this guards: the endpoint used to write the alias
        // `contract` into `attachable_type`, so every uploaded file was saved
        // and then never listed again.
        $contract = Contract::factory()->create();

        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/attachments', [
            'attachable_type' => 'contract',
            'attachable_id' => $contract->id,
            'file' => UploadedFile::fake()->create('signed.pdf', 32, 'application/pdf'),
        ])->assertCreated();

        $this->assertSame(1, $contract->attachments()->count());
        $this->assertSame(Contract::class, Attachment::firstOrFail()->attachable_type);
    }
}
