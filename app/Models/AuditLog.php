<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in the audit trail: an actor, an event, the affected record, and
 * the before/after values.
 *
 * Entries are append-only — nothing in the app updates or edits a row, which is
 * why the table carries no updated_at. Pruning old entries is the only write
 * besides the insert (see `audit:prune`).
 */
class AuditLog extends Model
{
    /** Audit rows are never modified, so there is no updated_at column. */
    public const UPDATED_AT = null;

    // ─── Event names ─────────────────────────────────────────────
    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGIN_FAILED = 'login_failed';
    public const EVENT_LOGIN_BLOCKED = 'login_blocked';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_PASSWORD_CHANGED = 'password_changed';
    public const EVENT_PASSWORD_RESET = 'password_reset';
    public const EVENT_ROLES_CHANGED = 'roles_changed';
    public const EVENT_PERMISSIONS_CHANGED = 'permissions_changed';

    /**
     * Short, stable aliases for auditable classes.
     *
     * The column stores the fully-qualified class name (so morphTo resolves),
     * but the API speaks these aliases: they are what the UI filters on and
     * they survive a namespace refactor.
     *
     * @var array<string, class-string<Model>>
     */
    public const TYPE_ALIASES = [
        'user' => User::class,
        'employee' => Employee::class,
        'company' => Company::class,
        'contact' => Contact::class,
        'contract' => Contract::class,
        'payment' => Payment::class,
        'service' => Service::class,
        'attachment' => Attachment::class,
        'weekly_report' => WeeklyReport::class,
        'employee_leave' => EmployeeLeave::class,
        'employee_overtime' => EmployeeOvertime::class,
        'server_subscription' => ServerSubscription::class,
        'sm_package' => \App\Models\SocialMedia\SmPackage::class,
        'content_plan' => \App\Models\SocialMedia\ContentPlan::class,
        'content_item' => \App\Models\SocialMedia\ContentItem::class,
        'photo_session' => \App\Models\SocialMedia\PhotoSession::class,
        'role' => \Spatie\Permission\Models\Role::class,
    ];

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The account that performed the action, when it still exists.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The affected record, when it has not been deleted since.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The short alias for this entry's auditable class, or null for auth events.
     */
    public function getTypeAliasAttribute(): ?string
    {
        return $this->auditable_type ? static::aliasFor($this->auditable_type) : null;
    }

    /**
     * Resolve a class name to its short alias, falling back to a snake_cased
     * basename for classes missing from the map.
     */
    public static function aliasFor(string $class): string
    {
        $alias = array_search($class, static::TYPE_ALIASES, true);

        return $alias !== false ? $alias : \Illuminate\Support\Str::snake(class_basename($class));
    }

    /**
     * Resolve an incoming filter value — alias or FQCN — to a class name.
     * Returns null when it matches nothing, so the caller can 422 instead of
     * silently listing everything.
     */
    public static function classForAlias(string $value): ?string
    {
        if (isset(static::TYPE_ALIASES[$value])) {
            return static::TYPE_ALIASES[$value];
        }

        return in_array($value, static::TYPE_ALIASES, true) ? $value : null;
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeForRecord(Builder $query, string $type, int|string $id): Builder
    {
        return $query->where('auditable_type', static::classForAlias($type) ?? $type)
            ->where('auditable_id', $id);
    }

    public function scopeEvent(Builder $query, string|array $event): Builder
    {
        return $query->whereIn('event', (array) $event);
    }
}
