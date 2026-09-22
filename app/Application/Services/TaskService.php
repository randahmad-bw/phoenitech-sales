<?php

namespace App\Application\Services;

use App\Application\Support\AccessScope;
use App\Exceptions\BusinessRuleException;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Assigning work, and following it.
 *
 * Two rules this service exists to hold:
 *
 * 1. **A task always has an owner.** Creating one without an assignee is
 *    refused here rather than left to the form, because an unowned task is the
 *    thing every to-do list dies of.
 * 2. **Timestamps are the system's, not the client's.** `started_at` and
 *    `completed_at` are written when the status actually moves, so "when was
 *    this finished" is answered by the record instead of by whoever typed
 *    fastest.
 *
 * Who may move a task is not decided here — the route's permission middleware
 * and `AccessScope` do that. This decides what a move means.
 */
class TaskService
{
    public function __construct(
        private NotificationService $notifications,
        private AttachmentService $attachments,
    ) {}

    /**
     * A page of tasks, newest pressure first.
     *
     * The order is the whole point of the screen: overdue work, then what is
     * due soonest, then priority. A manager opening this should not have to
     * sort it to see what is late.
     *
     * @param  array<string, mixed>  $filters
     * @param  int|null  $restrictToEmployeeId  Non-null limits the page to one person's tasks.
     * @return LengthAwarePaginator<Task>
     */
    public function list(array $filters = [], ?int $restrictToEmployeeId = null): LengthAwarePaginator
    {
        return $this->mostPressingFirst(
            $this->query($filters, $restrictToEmployeeId)
                ->with(['assignee', 'creator', 'company'])
                ->withCount($this->progressCounts())
        )->paginate((int) ($filters['per_page'] ?? 25));
    }

    /**
     * One task with everything the detail panel shows.
     */
    public function find(int $id): Task
    {
        return Task::with(['assignee', 'creator', 'company', 'comments.author', 'attachments', 'items'])
            ->withCount($this->progressCounts())
            ->findOrFail($id);
    }

    /**
     * Counters for the header strip.
     *
     * Scoped the same way the listing is, so an employee's numbers describe
     * their own plate and a manager's describe the company's.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function summary(array $filters = [], ?int $restrictToEmployeeId = null): array
    {
        $base = fn (): Builder => $this->query($filters, $restrictToEmployeeId);
        $today = now()->toDateString();

        return [
            'total' => (clone $base())->count(),
            'todo' => (clone $base())->where('status', Task::STATUS_TODO)->count(),
            'in_progress' => (clone $base())->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'done' => (clone $base())->where('status', Task::STATUS_DONE)->count(),
            'overdue' => (clone $base())->open()->whereNotNull('due_date')->whereDate('due_date', '<', $today)->count(),
            'due_today' => (clone $base())->open()->whereDate('due_date', $today)->count(),
        ];
    }

    /**
     * Assign a new task.
     *
     * It always starts at `todo`, whatever the client sends: a task created as
     * "done" records no work, and one created as "in progress" back-dates a
     * start that never happened.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Task
    {
        $this->guardAssignee($data['assigned_to'] ?? null);

        $task = Task::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to' => $data['assigned_to'],
            'created_by' => $actor->id,
            'company_id' => $data['company_id'] ?? null,
            'status' => Task::STATUS_TODO,
            'priority' => $data['priority'] ?? 'normal',
            'due_date' => $data['due_date'] ?? null,
        ]);

        $this->seedItems($task, $data['items'] ?? [], $actor);

        $this->announceAssignment($task, $actor);

        return $task->load(['assignee', 'creator', 'company', 'items']);
    }

    /**
     * Write the lines a task was created with.
     *
     * Blank entries are dropped rather than refused: the form adds an empty row
     * for the next line, and the last one is nearly always still empty when the
     * manager presses save.
     *
     * @param  array<int, string>  $titles
     */
    private function seedItems(Task $task, array $titles, User $actor): void
    {
        $position = 0;

        foreach ($titles as $title) {
            $title = trim((string) $title);

            if ($title === '') {
                continue;
            }

            TaskItem::create([
                'task_id' => $task->id,
                'title' => $title,
                'position' => ++$position,
                'created_by' => $actor->id,
            ]);
        }
    }

    /**
     * Change a task's particulars — not its status.
     *
     * Status has its own method because moving a task writes timestamps, and a
     * generic update that also happened to set a status would write them
     * silently or not at all depending on which fields the form happened to
     * send.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data, User $actor): Task
    {
        if (array_key_exists('assigned_to', $data)) {
            $this->guardAssignee($data['assigned_to']);
        }

        if ($task->isFinished() && $data !== []) {
            throw new BusinessRuleException(
                'This task is already closed. Reopen it before changing it.',
                'TASK_CLOSED'
            );
        }

        $previousAssignee = (int) $task->assigned_to;

        $task->update($data);

        // Handing work to somebody else is the same event as assigning it for
        // the first time, and the new owner needs telling either way.
        if ((int) $task->assigned_to !== $previousAssignee) {
            $this->announceAssignment($task, $actor);
        }

        return $task->fresh(['assignee', 'creator', 'company']);
    }

    /**
     * Move a task to a new state.
     *
     * Reopening is allowed — work comes back — and clears the completion, so a
     * reopened task never carries a finish time for work that is still running.
     *
     * @param  array<string, mixed>  $data
     */
    public function changeStatus(Task $task, array $data, User $actor): Task
    {
        $status = $data['status'];

        if ($status === $task->status) {
            throw new BusinessRuleException(
                'The task is already in this state.',
                'TASK_STATUS_UNCHANGED'
            );
        }

        return DB::transaction(function () use ($task, $status, $data, $actor): Task {
            $task->update($this->transitionAttributes($task, $status, $data['completion_note'] ?? null));

            // The note goes on the thread as well, so the history of a task
            // reads as one conversation instead of a field nobody opens.
            if (! empty($data['completion_note'])) {
                $this->comment($task, $actor, $data['completion_note'], notify: false);
            }

            $this->announce($task, $actor, Notification::TYPE_TASK_STATUS, [
                'task' => $task->title,
                'actor' => $actor->name,
                'status' => $status,
            ]);

            return $task->fresh(['assignee', 'creator', 'company', 'comments.author']);
        });
    }

    // ─── The lines inside a task ─────────────────────────

    /**
     * Add a line to a task's checklist.
     *
     * Open to the person doing the work as well as to whoever assigned it,
     * because half of what anybody does in a day was not on the list when the
     * day started. `created_by` is what keeps the two apart afterwards.
     */
    public function addItem(Task $task, string $title, User $actor): TaskItem
    {
        if ($task->isFinished()) {
            throw new BusinessRuleException(
                'This task is already closed. Reopen it before adding to it.',
                'TASK_CLOSED'
            );
        }

        return TaskItem::create([
            'task_id' => $task->id,
            'title' => $title,
            // Appended, not inserted: `max + 1` leaves the order management
            // wrote intact instead of renumbering every row on every add.
            'position' => (int) $task->items()->max('position') + 1,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * One line of a task, found through the relation.
     *
     * Scoped on purpose: an id belonging to another task's checklist is a 404
     * here rather than a tick over there.
     */
    public function itemOf(Task $task, int $id): TaskItem
    {
        return $task->items()->findOrFail($id);
    }

    /**
     * Rename a line, or tick it.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(Task $task, TaskItem $item, array $data, User $actor): TaskItem
    {
        return DB::transaction(function () use ($task, $item, $data, $actor): TaskItem {
            $attributes = [];

            if (array_key_exists('title', $data)) {
                $attributes['title'] = $data['title'];
            }

            if (array_key_exists('done', $data)) {
                $done = (bool) $data['done'];

                /*
                 * Only a real change writes the time. Two people pressing the
                 * same box — or one person pressing it twice on a slow
                 * connection — must not move "when was this finished".
                 */
                if ($done !== $item->isDone()) {
                    $attributes['completed_at'] = $done ? now() : null;
                    $attributes['completed_by'] = $done ? $actor->id : null;
                }
            }

            if ($attributes !== []) {
                $item->update($attributes);
            }

            $this->syncStatusWithItems($task->refresh(), $actor);

            return $item->refresh();
        });
    }

    /**
     * Remove a line.
     *
     * Whether the caller *may* remove this one is decided by the controller —
     * a line you were given is not a line you can drop. What happens after is
     * decided here: removing the last open line finishes the task, the same as
     * ticking it would.
     */
    public function deleteItem(Task $task, TaskItem $item, User $actor): void
    {
        DB::transaction(function () use ($task, $item, $actor): void {
            $item->delete();

            $this->syncStatusWithItems($task->refresh(), $actor);
        });
    }

    /**
     * The lines one person still has open, most pressing task first.
     *
     * This is what the check-out dialog asks for. Not a board — the short list
     * of what the day was supposed to contain, so closing the day is a few taps
     * instead of a paragraph nobody wants to write at six in the evening.
     *
     * @return Collection<int, TaskItem>
     */
    public function checklistFor(int $employeeId, int $limit = 60): Collection
    {
        return TaskItem::query()
            // Qualified: both tables have a `completed_at`, and the join below
            // makes the bare column name ambiguous.
            ->whereNull('task_items.completed_at')
            ->select('task_items.*')
            ->join('tasks', 'tasks.id', '=', 'task_items.task_id')
            ->where('tasks.assigned_to', $employeeId)
            ->whereIn('tasks.status', Task::OPEN_STATUSES)
            ->with('task')
            ->orderByRaw($this->pressureOrdering())
            ->orderBy('task_items.position')
            ->orderBy('task_items.id')
            ->limit($limit)
            ->get();
    }

    /**
     * Tick several lines at once, on the way out of the door.
     *
     * The employee is part of the query rather than a check afterwards: an id
     * that belongs to somebody else's task is simply not found, so a
     * hand-written request cannot close another person's work. Lines already
     * ticked are skipped for the same reason `updateItem` skips them — the time
     * on a finished line is not rewritten.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, TaskItem>
     */
    public function completeItemsFor(Employee $employee, array $ids, User $actor): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return DB::transaction(function () use ($employee, $ids, $actor): Collection {
            $items = TaskItem::whereIn('task_items.id', $ids)
                ->whereNull('task_items.completed_at')
                ->whereHas('task', fn ($q) => $q->where('assigned_to', $employee->id))
                ->with('task')
                ->get();

            $now = now();

            foreach ($items as $item) {
                $item->update(['completed_at' => $now, 'completed_by' => $actor->id]);
            }

            // One sync per task, not per line: five ticks on the same task
            // should send one "finished" notice, not five.
            foreach ($items->pluck('task')->filter()->unique('id') as $task) {
                $this->syncStatusWithItems($task->refresh(), $actor);
            }

            return $items;
        });
    }

    /**
     * Delete a task, and the files hanging off it.
     *
     * Comments go by foreign key; attachments and notifications are
     * polymorphic, so nothing in the database removes them — the files would
     * stay on disk forever and the bell would keep offering a task that is not
     * there. Both are cleared here.
     */
    public function delete(Task $task): void
    {
        foreach ($task->attachments as $attachment) {
            $this->attachments->deleteAttachment($attachment);
        }

        // Notices about a task that no longer exists are a bell that leads
        // nowhere. Nothing cascades them: the subject is polymorphic.
        $this->notifications->purgeSubject($task);

        $task->delete();
    }

    /**
     * Put a file on a task.
     */
    public function attach(Task $task, UploadedFile $file): Attachment
    {
        return $this->attachments->store($file, Task::class, $task->id);
    }

    /**
     * Remove a file from a task.
     *
     * Scoped through the relation on purpose: an id that belongs to some other
     * record's attachment is a 404 here rather than a deletion over there.
     */
    public function detach(Task $task, int $attachmentId): void
    {
        $this->attachments->deleteAttachment($task->attachments()->findOrFail($attachmentId));
    }

    /**
     * Add a line to the follow-up thread.
     *
     * `$notify` is false only when the line is a by-product of something that
     * announces itself — a completion note, which already sends a "moved"
     * notice. Without it, closing a task with a note sends two.
     */
    public function comment(Task $task, User $actor, string $body, bool $notify = true): TaskComment
    {
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'author_name' => $actor->name,
            'body' => $body,
        ]);

        if ($notify) {
            $this->announce($task, $actor, Notification::TYPE_TASK_COMMENT, [
                'task' => $task->title,
                'actor' => $actor->name,
                'excerpt' => Str::limit($body, 120),
            ]);
        }

        return $comment;
    }

    /**
     * What the landing dashboard says about tasks.
     *
     * Null for an account with no task permission at all — the dashboard then
     * simply has no task section, rather than an empty one implying there is
     * nothing to do.
     *
     * The focus list is short on purpose. A dashboard that reprints the whole
     * board is the board, and the reason to put tasks on the landing screen is
     * to answer one question before anything else: *is something late.*
     *
     * @return array{scope: string, summary: array<string, int>, focus: Collection<int, Task>}|null
     */
    public function digest(?User $user, int $limit = 5): ?array
    {
        if ($user === null || ! ($user->can('tasks.view_all') || $user->can('tasks.view_own'))) {
            return null;
        }

        $scope = AccessScope::ownEmployeeId($user, 'tasks.view_all');

        $focus = $this->mostPressingFirst(
            $this->query([], $scope)
                ->open()
                ->with(['assignee', 'company'])
                ->withCount($this->progressCounts())
        )->limit($limit)->get();

        return [
            // Which board this is — the company's or the reader's own. The
            // screen says so out loud, because "3 overdue" means very
            // different things to a manager and to the person holding them.
            'scope' => $scope === null ? 'all' : 'own',
            'summary' => $this->summary([], $scope),
            'focus' => $focus,
        ];
    }

    /**
     * Work sitting on one person's plate that nobody has picked up yet.
     *
     * Always the caller's own, never a board — even for a manager holding
     * `tasks.view_all`. This feeds a badge and a strip that say *you have
     * something waiting*, and a manager cannot start somebody else's task, so
     * counting the company's untouched work there would be a red dot they can
     * never clear.
     *
     * Count and list in one answer because both readers are on screen at the
     * same moment: the sidebar wants the number, the landing strip wants the
     * first few titles, and two endpoints would poll twice for one fact.
     *
     * @return array{count: int, tasks: Collection<int, Task>}
     */
    public function notStartedFor(?User $user, int $limit = 5): array
    {
        $employeeId = $user?->employee?->id;

        // Not an error. An account with no employee profile is nobody's
        // assignee, so it has nothing waiting — the badge simply never shows.
        if ($employeeId === null) {
            return ['count' => 0, 'tasks' => new Collection];
        }

        $base = fn (): Builder => Task::query()
            ->where('assigned_to', $employeeId)
            ->where('status', Task::STATUS_TODO);

        return [
            'count' => $base()->count(),
            'tasks' => $this->mostPressingFirst(
                $base()->with('company')->withCount($this->progressCounts())
            )->limit($limit)->get(),
        ];
    }

    /**
     * Tell the new owner that work has landed on them.
     */
    private function announceAssignment(Task $task, User $actor): void
    {
        $this->notifications->pushToEmployee(
            (int) $task->assigned_to,
            Notification::TYPE_TASK_ASSIGNED,
            [
                'task' => $task->title,
                'actor' => $actor->name,
                'due_date' => $task->due_date?->format('Y-m-d'),
            ],
            self::link($task),
            $task,
            $actor,
        );
    }

    /**
     * Tell everyone with a stake in this task — except whoever just acted.
     *
     * The stake is held by two people: the one doing the work and the one who
     * asked for it. Notifying the actor is filtered out by the notification
     * service, and the two are de-duplicated here for the common case of
     * somebody assigning work to themselves.
     *
     * @param  array<string, mixed>  $data
     */
    private function announce(Task $task, User $actor, string $type, array $data): void
    {
        $link = self::link($task);
        $assigneeUser = $task->assignee?->user;

        $this->notifications->push($assigneeUser, $type, $data, $link, $task, $actor);

        $creator = $task->creator;

        if ($creator !== null && $creator->id !== $assigneeUser?->id) {
            $this->notifications->push($creator, $type, $data, $link, $task, $actor);
        }
    }

    /**
     * Where a notification about this task takes the reader.
     *
     * The tasks screen opens the matching task when it sees `?task=`, so one
     * click goes from the bell to the thing itself instead of to a list the
     * reader then has to search.
     */
    public static function link(Task $task): string
    {
        return '/tasks?task='.$task->id;
    }

    /**
     * Keep a task's state honest about its lines.
     *
     * The lines are where the work actually is, so the task follows them rather
     * than the other way round: the first tick starts a task nobody had
     * started, the last tick finishes it, and un-ticking a line on a finished
     * task puts it back in progress.
     *
     * Two deliberate exemptions:
     *
     *  - **A task with no lines is left entirely alone.** Its status is
     *    whatever somebody set, which is the right answer for one-motion work.
     *  - **Cancelled is never touched.** Work that was called off does not come
     *    back because somebody tidied a checkbox.
     */
    private function syncStatusWithItems(Task $task, User $actor): void
    {
        if ($task->status === Task::STATUS_CANCELLED) {
            return;
        }

        $total = $task->items()->count();

        if ($total === 0) {
            return;
        }

        $done = $task->items()->done()->count();

        $target = match (true) {
            $done === $total => Task::STATUS_DONE,
            $done > 0 => Task::STATUS_IN_PROGRESS,
            // Nothing ticked. Only a *finished* task is wrong about that; a
            // task somebody started and has not delivered on stays started.
            $task->status === Task::STATUS_DONE => Task::STATUS_IN_PROGRESS,
            default => $task->status,
        };

        if ($target === $task->status) {
            return;
        }

        $task->update($this->transitionAttributes($task, $target));

        $this->announce($task, $actor, Notification::TYPE_TASK_STATUS, [
            'task' => $task->title,
            'actor' => $actor->name,
            'status' => $target,
        ]);
    }

    /**
     * What moving a task to `$status` writes, besides the status itself.
     *
     * Shared by the explicit move and by the checklist following its lines, so
     * a task finished by ticking the last box carries exactly the timestamps a
     * task finished by pressing the button does.
     *
     * @return array<string, mixed>
     */
    private function transitionAttributes(Task $task, string $status, ?string $note = null): array
    {
        $attributes = ['status' => $status];

        if ($status === Task::STATUS_IN_PROGRESS) {
            // Only the first start counts; picking a task back up does not
            // restart the clock on it.
            $attributes['started_at'] = $task->started_at ?? now();
            $attributes['completed_at'] = null;
            $attributes['completion_note'] = null;
        }

        if ($status === Task::STATUS_TODO) {
            $attributes['started_at'] = null;
            $attributes['completed_at'] = null;
            $attributes['completion_note'] = null;
        }

        if ($status === Task::STATUS_DONE || $status === Task::STATUS_CANCELLED) {
            $attributes['completed_at'] = now();
            $attributes['completion_note'] = $note;
        }

        return $attributes;
    }

    /**
     * The counters every task-carrying screen needs: the thread length, how
     * many lines the task has, and how many are ticked. Kept in one place so
     * the listing, the detail call and the dashboard digest cannot disagree
     * about a task's progress.
     *
     * @return array<int|string, mixed>
     */
    private function progressCounts(): array
    {
        return [
            'comments',
            'items',
            'items as done_items_count' => fn ($q) => $q->whereNotNull('completed_at'),
        ];
    }

    /**
     * The filtered, scoped base query both the listing and the counters use.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    private function query(array $filters, ?int $restrictToEmployeeId): Builder
    {
        return Task::query()
            ->when($restrictToEmployeeId !== null, fn ($q) => $q->where('assigned_to', $restrictToEmployeeId))
            ->when($filters['status'] ?? null, function ($q, $status) {
                // `open` is not a stored status but the question people
                // actually ask: everything still on someone's plate.
                return $status === 'open'
                    ? $q->open()
                    : $q->where('status', $status);
            })
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['assigned_to'] ?? null, fn ($q, $id) => $q->where('assigned_to', $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when(($filters['overdue'] ?? null) === true, fn ($q) => $q->overdue())
            ->when($filters['due_from'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
            ->when($filters['due_to'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '<=', $date))
            ->when($filters['search'] ?? null, function ($q, $term) {
                $like = '%'.$term.'%';

                return $q->where(fn ($inner) => $inner->where('title', 'like', $like)->orWhere('description', 'like', $like));
            });
    }

    /**
     * The order every task list is read in: pressure, then priority.
     *
     * One helper rather than the three calls repeated per query — the listing,
     * the dashboard digest and the waiting strip all have to agree on what
     * "most pressing" means, and three copies of an ordering is how they stop
     * agreeing.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private function mostPressingFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw($this->pressureOrdering())
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('id');
    }

    /**
     * Open work first, then by how close the deadline is.
     *
     * Written as raw SQL because it is one ordering over three facts — closed
     * or not, dated or not, and the date itself — and splitting it into three
     * `orderBy` calls would put undated tasks above overdue ones.
     */
    private function pressureOrdering(): string
    {
        $today = now()->toDateString();

        return "CASE
            WHEN status IN ('done', 'cancelled') THEN 3
            WHEN due_date IS NOT NULL AND due_date < '{$today}' THEN 0
            WHEN due_date IS NOT NULL THEN 1
            ELSE 2
        END, due_date IS NULL, due_date ASC";
    }

    /**
     * Refuse a task with no owner, or one pointed at somebody who is not there.
     */
    private function guardAssignee(mixed $employeeId): void
    {
        if (! $employeeId) {
            throw new BusinessRuleException(
                'A task needs someone responsible for it.',
                'TASK_NEEDS_ASSIGNEE'
            );
        }

        if (! Employee::whereKey($employeeId)->exists()) {
            throw new BusinessRuleException(
                'The employee this task would be assigned to no longer exists.',
                'TASK_ASSIGNEE_MISSING'
            );
        }
    }
}
