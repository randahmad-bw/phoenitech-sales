<?php

namespace App\Application\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read side of the audit trail: filtering, and the option lists the filter UI
 * needs. There is deliberately no write method here — entries are only ever
 * created through AuditLogger, and never edited.
 */
class AuditLogService
{
    /** Ceiling on page size so a single request cannot pull the whole table. */
    private const MAX_PER_PAGE = 100;

    /**
     * Filtered, newest-first listing.
     *
     * Supported filters: user_id, event (name or comma-separated list),
     * type (short alias or FQCN), record_id, date_from, date_to, search.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::with('user:id,name,email');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['event'])) {
            $events = is_array($filters['event'])
                ? $filters['event']
                : array_filter(explode(',', (string) $filters['event']));

            $query->whereIn('event', $events);
        }

        if (! empty($filters['type'])) {
            // An unknown alias resolves to itself and therefore matches nothing,
            // rather than silently widening the result to every record type.
            $query->where('auditable_type', AuditLog::classForAlias($filters['type']) ?? $filters['type']);
        }

        if (! empty($filters['record_id'])) {
            $query->where('auditable_id', $filters['record_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('auditable_label', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) ($filters['per_page'] ?? 25) ?: 25, self::MAX_PER_PAGE);

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): AuditLog
    {
        return AuditLog::with('user:id,name,email')->findOrFail($id);
    }

    /**
     * The full trail for one record, newest first.
     */
    public function forRecord(string $type, int|string $id, int $perPage = 25): LengthAwarePaginator
    {
        return AuditLog::with('user:id,name,email')
            ->forRecord($type, $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Values to populate the filter dropdowns: only events and record types that
     * actually occur in the table, plus the users who appear as actors.
     */
    public function filterOptions(): array
    {
        $events = AuditLog::query()->distinct()->orderBy('event')->pluck('event')->all();

        $types = AuditLog::query()
            ->whereNotNull('auditable_type')
            ->distinct()
            ->pluck('auditable_type')
            ->map(fn (string $class) => AuditLog::aliasFor($class))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $actorIds = AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id');

        $users = User::whereIn('id', $actorIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->all();

        return [
            'events' => $events,
            'types' => $types,
            'users' => $users,
        ];
    }

    /**
     * Delete entries older than the given number of days. Returns the row count.
     * A retention of 0 means "keep everything" and deletes nothing.
     */
    public function prune(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
