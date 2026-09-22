<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One piece of work, assigned to one person.
 *
 * The state machine is deliberately four values wide. Every extra column on a
 * board is a decision someone has to make before they can move a card, and the
 * only distinctions that change what anybody does are: not started, being
 * worked on, finished, and dropped.
 *
 * "Overdue" is not one of them — it is not a status but a fact about an open
 * task whose date has passed, derived here rather than stored, so it can never
 * drift out of date the way a nightly job would leave it.
 */
class Task extends Model
{
    use Auditable, HasFactory;

    /** Assigned, not started. */
    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    /** Dropped. Kept on file: what was called off is part of the record. */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    /** The states that still need someone's attention. */
    public const OPEN_STATUSES = [self::STATUS_TODO, self::STATUS_IN_PROGRESS];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'title',
        'description',
        'assigned_to',
        'created_by',
        'company_id',
        'status',
        'priority',
        'due_date',
        'started_at',
        'completed_at',
        'completion_note',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ─── Relations ───

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    /**
     * The lines inside the task, in the order they were written.
     *
     * A task may have none — a one-motion job needs no checklist — and the
     * screens read `items_count` rather than assuming either way.
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Files that belong to the task — the brief that came with it, and the
     * work that came back.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    // ─── State ───

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isFinished(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * Past its due date and still open.
     *
     * A finished task is never overdue, however late it was completed — the
     * flag exists to point at work that still needs doing today.
     */
    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->due_date !== null
            && $this->due_date->startOfDay()->isBefore(now()->startOfDay());
    }

    /** Open and due today. */
    public function isDueToday(): bool
    {
        return $this->isOpen()
            && $this->due_date !== null
            && $this->due_date->isSameDay(now());
    }

    // ─── Scopes ───

    /** @param  Builder<Task>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /** @param  Builder<Task>  $query */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    /**
     * The label the audit trail uses for this record.
     */
    public function auditLabel(): string
    {
        return $this->title;
    }
}
