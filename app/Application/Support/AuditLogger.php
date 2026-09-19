<?php

namespace App\Application\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single entry point for writing audit entries.
 *
 * Everything that records history goes through here — the Auditable model trait,
 * the auth controller, and the services that change roles or passwords — so the
 * actor resolution, request context, and value sanitising are defined once.
 *
 * Writing an audit entry must never break the action being audited: a failure
 * here is logged and swallowed.
 */
class AuditLogger
{
    /**
     * Runtime switch, independent of config. Used by seeders, imports, and the
     * tests that assert *absence* of noise.
     */
    private static bool $enabled = true;

    /** Actor override, used when the acting user is not the authenticated one. */
    private static ?User $actor = null;

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled && (bool) config('audit.enabled', true);
    }

    /**
     * Run a callback with auditing switched off, restoring the previous state
     * afterwards even if the callback throws.
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    /**
     * Attribute the next entries to a specific user (e.g. a queued job acting
     * on someone's behalf). Pass null to go back to the authenticated user.
     */
    public static function actingAs(?User $user): void
    {
        self::$actor = $user;
    }

    /**
     * Write one entry.
     *
     * @param  string      $event       One of AuditLog::EVENT_*
     * @param  Model|null  $auditable   The affected record, if any.
     * @param  array|null  $oldValues   Values before the change.
     * @param  array|null  $newValues   Values after the change.
     * @param  string|null $label       Human-readable name of the record; derived when omitted.
     * @param  User|null   $actor       Overrides the resolved actor (needed for login events).
     */
    public static function record(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $label = null,
        ?User $actor = null,
    ): ?AuditLog {
        if (! self::isEnabled()) {
            return null;
        }

        try {
            $actor ??= self::resolveActor();
            $request = self::requestContext();

            return AuditLog::create([
                'user_id' => $actor?->getKey(),
                'user_name' => $actor?->name,
                'user_email' => $actor?->email,
                'event' => $event,
                'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
                'auditable_id' => $auditable?->getKey(),
                'auditable_label' => $label ?? self::labelFor($auditable),
                'old_values' => self::sanitize($oldValues),
                'new_values' => self::sanitize($newValues),
                'ip_address' => $request['ip'],
                'user_agent' => $request['user_agent'],
                'url' => $request['url'],
                'method' => $request['method'],
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Never let the trail break the transaction it is describing.
            Log::warning('Failed to write audit log entry', [
                'event' => $event,
                'auditable' => $auditable ? $auditable::class.'#'.$auditable->getKey() : null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The user to attribute an action to: an explicit override first, then the
     * authenticated user. Null in console context.
     */
    private static function resolveActor(): ?User
    {
        if (self::$actor) {
            return self::$actor;
        }

        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Request metadata, empty when running in the console (scheduler, seeders).
     *
     * @return array{ip: ?string, user_agent: ?string, url: ?string, method: ?string}
     */
    private static function requestContext(): array
    {
        if (app()->runningInConsole()) {
            return ['ip' => null, 'user_agent' => null, 'url' => null, 'method' => null];
        }

        $request = request();

        return [
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'url' => Str::limit($request->fullUrl(), 255, ''),
            'method' => $request->method(),
        ];
    }

    /**
     * A human-readable name for the record, used so the trail still identifies
     * a row after it is deleted.
     */
    public static function labelFor(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        if (method_exists($model, 'auditLabel')) {
            return Str::limit((string) $model->auditLabel(), 255, '');
        }

        foreach (['contract_number', 'name', 'name_ar', 'title', 'domain', 'email'] as $attribute) {
            if (! empty($model->getAttribute($attribute))) {
                return Str::limit((string) $model->getAttribute($attribute), 255, '');
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    /**
     * Drop globally excluded attributes and cap long values so one entry cannot
     * carry a whole document into the table.
     */
    private static function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $excluded = (array) config('audit.excluded_attributes', []);
        $max = (int) config('audit.max_value_length', 2000);

        $clean = [];
        foreach ($values as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            if (is_string($value) && mb_strlen($value) > $max) {
                $value = mb_substr($value, 0, $max).'…';
            }

            $clean[$key] = $value;
        }

        return $clean === [] ? null : $clean;
    }
}
