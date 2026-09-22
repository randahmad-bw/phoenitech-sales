<?php

namespace App\Console\Commands;

use App\Application\Services\NotificationService;
use App\Application\Services\TaskService;
use App\Models\Notification;
use App\Models\Task;
use Illuminate\Console\Command;

/**
 * The morning nudge: what is due today, and what is already late.
 *
 * A due date nobody is reminded of is a field, not a deadline. This runs once
 * each morning and tells each assignee about their own open work — one notice
 * per task per day, enforced by `pushOncePerDay`, so running the job twice (or
 * catching up after a night the scheduler was down) never buries anybody.
 *
 * Tasks whose assignee has no login are skipped silently: the work is still
 * assigned, there is simply no one to tell.
 */
class RemindTaskOwners extends Command
{
    protected $signature = 'tasks:remind';

    protected $description = 'Notify assignees about tasks that are due today or overdue.';

    public function handle(NotificationService $notifications): int
    {
        $today = now()->toDateString();
        $sent = 0;

        $tasks = Task::query()
            ->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today)
            ->with('assignee')
            ->get();

        foreach ($tasks as $task) {
            $overdue = $task->isOverdue();

            $notification = $notifications->pushOncePerDay(
                (int) $task->assigned_to,
                $overdue ? Notification::TYPE_TASK_OVERDUE : Notification::TYPE_TASK_DUE_TODAY,
                [
                    'task' => $task->title,
                    'due_date' => $task->due_date?->format('Y-m-d'),
                ],
                TaskService::link($task),
                $task,
            );

            if ($notification !== null) {
                $sent++;
            }
        }

        $this->info("Task reminders sent: {$sent} (of {$tasks->count()} task(s) due or overdue).");

        return self::SUCCESS;
    }
}
