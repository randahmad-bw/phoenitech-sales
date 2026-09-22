<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line on a task's checklist.
 *
 * `is_own` is sent rather than left to the client to work out from
 * `created_by`: whether a line may be removed depends on whether the reader
 * wrote it, the server already decides that when the delete arrives, and a
 * button that disagrees with the API is worse than no button.
 */
class TaskItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'title' => $this->title,
            'position' => $this->position,

            'is_done' => $this->isDone(),
            'completed_at' => $this->completed_at?->toISOString(),
            'completed_by' => $this->completed_by,

            // A line the reader added themselves — theirs to remove. A line
            // that came with the task is not.
            'created_by' => $this->created_by,
            'is_own' => $request->user() !== null && (int) $this->created_by === (int) $request->user()->id,

            // Only the check-out checklist loads the task: there the lines
            // come from several tasks at once and a bare "post the story" does
            // not say which job it belongs to. Inside a task, it is noise.
            'task_title' => $this->whenLoaded('task', fn () => $this->task?->title),
            'task_priority' => $this->whenLoaded('task', fn () => $this->task?->priority),
            'task_due_date' => $this->whenLoaded('task', fn () => $this->task?->due_date?->format('Y-m-d')),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
