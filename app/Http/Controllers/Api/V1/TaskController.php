<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\TaskService;
use App\Application\Support\AccessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Requests\StoreTaskItemRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\TaskFilterRequest;
use App\Http\Requests\UpdateTaskItemRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\TaskCollection;
use App\Http\Resources\TaskCommentResource;
use App\Http\Resources\TaskItemResource;
use App\Http\Resources\TaskResource;
use App\Http\Responses\ApiResponse;
use App\Models\Task;
use App\Models\TaskItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tasks: assigning work and following it.
 *
 * One screen serves two readers, and the difference between them is a single
 * scope decision made here: `tasks.view_all` sees the company's work,
 * everybody else sees their own. The same endpoints serve both, so there is no
 * second "my tasks" API that could drift from this one.
 *
 * Moving a task is deliberately not part of editing it. An assignee who cannot
 * reassign work or change its deadline can still say they have started it and
 * that it is done — which is the whole of what the system asks of them.
 */
class TaskController extends Controller
{
    public function __construct(private TaskService $service) {}

    /**
     * GET tasks — a page of work, most pressing first.
     */
    public function index(TaskFilterRequest $request): JsonResponse
    {
        $tasks = $this->service->list(
            $request->filters(),
            AccessScope::ownEmployeeId($request->user(), 'tasks.view_all')
        );

        return ApiResponse::paginated(new TaskCollection($tasks), 'Tasks retrieved successfully.');
    }

    /**
     * GET tasks/summary — the counters above the list, scoped like the list.
     */
    public function summary(TaskFilterRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->summary(
                $request->filters(),
                AccessScope::ownEmployeeId($request->user(), 'tasks.view_all')
            ),
            'Task summary retrieved successfully.'
        );
    }

    /**
     * GET tasks/{task} — one task with its thread.
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        if (! $this->mayRead($request, $task)) {
            return ApiResponse::forbidden('You are not authorized to view this task.');
        }

        return ApiResponse::success(
            new TaskResource($this->service->find($task->id)),
            'Task retrieved successfully.'
        );
    }

    /**
     * POST tasks — assign work.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->service->create($request->validated(), $request->user());

        return ApiResponse::created(new TaskResource($task), 'Task assigned successfully.');
    }

    /**
     * PATCH tasks/{task} — change what the task is, who owns it, when it is due.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        return ApiResponse::success(
            new TaskResource($this->service->update($task, $request->validated(), $request->user())),
            'Task updated successfully.'
        );
    }

    /**
     * PATCH tasks/{task}/status — move it.
     *
     * Open to the person the task belongs to as well as to management, which
     * is the point: progress is reported by whoever is doing the work.
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        if (! $this->mayMove($request, $task)) {
            return ApiResponse::forbidden('You can only update the status of your own tasks.');
        }

        return ApiResponse::success(
            new TaskResource($this->service->changeStatus($task, $request->validated(), $request->user())),
            'Task status updated successfully.'
        );
    }

    /**
     * POST tasks/{task}/comments — add to the follow-up thread.
     */
    public function comment(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        if (! $this->mayRead($request, $task)) {
            return ApiResponse::forbidden('You are not authorized to comment on this task.');
        }

        $comment = $this->service->comment($task, $request->user(), $request->validated()['body']);

        return ApiResponse::created(new TaskCommentResource($comment), 'Comment added successfully.');
    }

    // ─── The lines inside a task ─────────────────────────

    /**
     * GET tasks/checklist — the caller's own open lines, across their tasks.
     *
     * Always the caller's, never a board: this feeds the check-out dialog, and
     * a manager closing their own day is closing their own day. Management
     * reads other people's work on the tasks screen.
     */
    public function checklist(Request $request): JsonResponse
    {
        $employee = $request->user()?->employee;

        // Not an error. An account with no employee profile has no day to
        // close and no lines to tick — the dialog simply shows none.
        if (! $employee) {
            return ApiResponse::success([], 'Checklist retrieved successfully.');
        }

        return ApiResponse::success(
            TaskItemResource::collection($this->service->checklistFor($employee->id)),
            'Checklist retrieved successfully.'
        );
    }

    /**
     * POST tasks/{task}/items — add a line.
     *
     * Behind the same permission as the thread rather than `tasks.edit`:
     * writing down a step of the work you were asked for is doing the task,
     * not editing it.
     */
    public function addItem(StoreTaskItemRequest $request, Task $task): JsonResponse
    {
        if (! $this->mayMove($request, $task)) {
            return ApiResponse::forbidden('You can only add to your own tasks.');
        }

        return ApiResponse::created(
            new TaskItemResource($this->service->addItem($task, $request->validated()['title'], $request->user())),
            'Checklist line added successfully.'
        );
    }

    /**
     * PATCH tasks/{task}/items/{item} — tick a line, or fix its wording.
     */
    public function updateItem(UpdateTaskItemRequest $request, Task $task, int $item): JsonResponse
    {
        if (! $this->mayMove($request, $task)) {
            return ApiResponse::forbidden('You can only update your own tasks.');
        }

        return ApiResponse::success(
            new TaskItemResource(
                $this->service->updateItem($task, $this->service->itemOf($task, $item), $request->validated(), $request->user())
            ),
            'Checklist line updated successfully.'
        );
    }

    /**
     * DELETE tasks/{task}/items/{item} — remove a line.
     */
    public function deleteItem(Request $request, Task $task, int $item): JsonResponse
    {
        if (! $this->mayMove($request, $task)) {
            return ApiResponse::forbidden('You can only change your own tasks.');
        }

        $line = $this->service->itemOf($task, $item);

        if (! $this->mayRemoveItem($request, $line)) {
            return ApiResponse::forbidden('This line was part of the task you were given. Tick it, or say why on the thread.');
        }

        $this->service->deleteItem($task, $line, $request->user());

        return ApiResponse::success(null, 'Checklist line removed successfully.');
    }

    /**
     * POST tasks/{task}/attachments — put a file on the task.
     *
     * Open to the assignee as well as to management: handing back the work you
     * were asked for is doing the task, not editing it.
     */
    public function attach(StoreTaskAttachmentRequest $request, Task $task): JsonResponse
    {
        if (! $this->mayMove($request, $task)) {
            return ApiResponse::forbidden('You can only attach files to your own tasks.');
        }

        return ApiResponse::created(
            new AttachmentResource($this->service->attach($task, $request->file('file'))),
            'File attached successfully.'
        );
    }

    /**
     * DELETE tasks/{task}/attachments/{attachment}.
     */
    public function detach(Request $request, Task $task, int $attachment): JsonResponse
    {
        if (! $this->mayMove($request, $task)) {
            return ApiResponse::forbidden('You can only remove files from your own tasks.');
        }

        $this->service->detach($task, $attachment);

        return ApiResponse::success(null, 'Attachment removed successfully.');
    }

    /**
     * DELETE tasks/{task}.
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->service->delete($task);

        return ApiResponse::success(null, 'Task deleted successfully.');
    }

    /**
     * Whether the caller may see this task: everyone's work, or their own.
     */
    private function mayRead(Request $request, Task $task): bool
    {
        return AccessScope::canAccessEmployee($request->user(), (int) $task->assigned_to, 'tasks.view_all');
    }

    /**
     * Whether the caller may move this task: management, or the person it
     * belongs to.
     */
    private function mayMove(Request $request, Task $task): bool
    {
        return AccessScope::canAccessEmployee($request->user(), (int) $task->assigned_to, 'tasks.edit');
    }

    /**
     * Whether the caller may delete this line.
     *
     * A line you wrote yourself is yours to remove. A line that came with the
     * task is not — otherwise an assignee can empty their own checklist and
     * report the task finished, which is exactly the thing the checklist exists
     * to make visible. Management, holding `tasks.edit`, removes either.
     */
    private function mayRemoveItem(Request $request, TaskItem $item): bool
    {
        return $request->user()?->can('tasks.edit')
            || (int) $item->created_by === (int) $request->user()?->id;
    }
}
