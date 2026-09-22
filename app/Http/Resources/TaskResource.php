<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A task as the screens read it.
 *
 * `is_overdue` and `is_open` are sent rather than left to the client: the
 * board, the counters and the row colouring must all agree on what "late"
 * means, and they only do if one place decides it.
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,

            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->whenLoaded('assignee', fn () => $this->assignee?->name, null),
            'assignee_department' => $this->whenLoaded('assignee', fn () => $this->assignee?->department, null),

            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn () => $this->creator?->name, null),

            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name, null),

            'status' => $this->status,
            'priority' => $this->priority,
            'is_open' => $this->isOpen(),
            'is_overdue' => $this->isOverdue(),
            'is_due_today' => $this->isDueToday(),

            'due_date' => $this->due_date?->format('Y-m-d'),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'completion_note' => $this->completion_note,

            // The checklist. `items_total` / `items_done` ride on every call so
            // a row can draw "2 / 4" without the listing shipping every line;
            // `items` itself is the detail call only.
            'items_total' => $this->whenCounted('items'),
            'items_done' => $this->whenCounted('done_items'),
            'items' => TaskItemResource::collection($this->whenLoaded('items')),

            // Present on a listing (withCount), replaced by the thread itself
            // on the detail call.
            'comments_count' => $this->whenCounted('comments'),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),

            // Files live on the detail call only: a listing that carried every
            // attachment would ship a page of rows nobody on it can see.
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
