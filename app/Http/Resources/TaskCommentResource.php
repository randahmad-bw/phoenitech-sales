<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line on a task's follow-up thread.
 *
 * The author name falls back to the stored copy, so a comment still says who
 * wrote it after the account behind it is gone.
 */
class TaskCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name, null) ?? $this->author_name,
            'body' => $this->body,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
