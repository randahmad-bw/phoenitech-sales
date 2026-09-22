<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One thing somebody should know about.
 *
 * Not `Auditable`: a notification is a copy of something the audit trail
 * already recorded. Auditing the copy would double every entry for nothing.
 *
 * The text is not stored. `type` says what happened and `data` carries the
 * handful of facts it happened to — the reader's screen turns the two into a
 * sentence in their own language. This is also why there is no `title`
 * column: it would have to be written in one language at the moment of
 * writing, and would be wrong for half the company.
 */
class Notification extends Model
{
    use HasFactory;

    /** Work was put on your plate. */
    public const TYPE_TASK_ASSIGNED = 'task_assigned';

    /** A task you assigned or own has moved. */
    public const TYPE_TASK_STATUS = 'task_status';

    /** Somebody wrote on a task you are part of. */
    public const TYPE_TASK_COMMENT = 'task_comment';

    /** An open task of yours is due today. */
    public const TYPE_TASK_DUE_TODAY = 'task_due_today';

    /** An open task of yours is past its date. */
    public const TYPE_TASK_OVERDUE = 'task_overdue';

    /** Somebody asked for leave and it is waiting on a decision. */
    public const TYPE_LEAVE_REQUESTED = 'leave_requested';

    protected $fillable = [
        'user_id',
        'type',
        'data',
        'link',
        'subject_type',
        'subject_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /** @param  Builder<Notification>  $query */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** @param  Builder<Notification>  $query */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
