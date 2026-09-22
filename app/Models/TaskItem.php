<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line inside a task.
 *
 * Not `Auditable`: a tick is not an administrative act, and auditing every one
 * of them would bury the trail that records who reassigned work and who
 * deleted it under a day's worth of checkboxes. `completed_by` and
 * `completed_at` keep the only part that anybody asks about later.
 *
 * `completed_at` is the done flag. There is no `is_done` column to disagree
 * with it, and no setter for it either — the service writes it when the tick
 * happens, so "when was this finished" is answered by the record.
 */
class TaskItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'title',
        'position',
        'completed_at',
        'completed_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    /** @param  Builder<TaskItem>  $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /** @param  Builder<TaskItem>  $query */
    public function scopeDone(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }
}
