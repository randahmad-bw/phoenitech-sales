<?php

namespace App\Models\Concerns;

use App\Application\Support\AuditLogger;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Records create / update / delete history for a model into the audit trail.
 *
 * Add `use Auditable;` to a model and every change made through Eloquent is
 * logged with the acting user and a before/after diff. Two hooks customise it:
 *
 *   protected array $auditExclude = ['last_login_at'];   // extra noisy columns
 *   protected string $auditLabelAttribute = 'contract_number';
 *
 * Note that mass operations bypassing model events (`Model::query()->update()`,
 * raw SQL, `WithoutModelEvents` in seeders) are intentionally NOT recorded —
 * the trail describes user actions, not bulk maintenance.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $values = $model->filterAuditAttributes($model->getAttributes());

            AuditLogger::record(
                AuditLog::EVENT_CREATED,
                $model,
                null,
                $values ?: null,
                $model->auditLabel(),
            );
        });

        static::updated(function (Model $model): void {
            $new = $model->filterAuditAttributes($model->getChanges());

            // Nothing meaningful changed (only timestamps or excluded columns).
            if ($new === []) {
                return;
            }

            $old = array_intersect_key($model->getRawOriginal(), $new);

            AuditLogger::record(
                AuditLog::EVENT_UPDATED,
                $model,
                $old ?: null,
                $new,
                $model->auditLabel(),
            );
        });

        static::deleted(function (Model $model): void {
            AuditLogger::record(
                AuditLog::EVENT_DELETED,
                $model,
                $model->filterAuditAttributes($model->getRawOriginal()) ?: null,
                null,
                $model->auditLabel(),
            );
        });
    }

    /**
     * Drop attributes this model should never put in the trail. The globally
     * excluded ones (secrets, timestamps) are removed later in AuditLogger.
     */
    public function filterAuditAttributes(array $attributes): array
    {
        $excluded = array_merge(
            (array) config('audit.excluded_attributes', []),
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * A human-readable name for this record, snapshotted into the entry so the
     * trail still identifies it once the row is gone.
     */
    public function auditLabel(): string
    {
        $candidates = property_exists($this, 'auditLabelAttribute')
            ? [$this->auditLabelAttribute]
            : ['contract_number', 'name', 'name_ar', 'title', 'domain', 'email'];

        foreach ($candidates as $attribute) {
            $value = $this->getAttribute($attribute);
            if (! empty($value)) {
                return (string) $value;
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }

    /**
     * This record's audit trail, newest first.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }
}
