<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of leave the company recognises.
 *
 * The catalogue used to be a constant in three languages at once — a PHP array,
 * a database enum and a TypeScript union — so it could only change by
 * deployment. It is a table now, and the settings screen edits it.
 *
 * Two things are worth knowing before touching this:
 *
 * 1. **`key` is the record, not `id`.** `employee_leaves.leave_type` stores the
 *    key, so changing one would detach every request already filed under it.
 *    The update path refuses to touch it.
 * 2. **`deducts_from_allowance` is the only behaviour a type carries.** It is
 *    what made `annual` different from the rest: its approved days come off the
 *    employee's yearly balance. Everything else — approval flow, working-day
 *    counting, the effect on the attendance calendar — is identical for every
 *    type, and always was.
 */
class LeaveType extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'is_active',
        'deducts_from_allowance',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deducts_from_allowance' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The audit trail names a type by its Arabic label — the one the person
     * who changed it was reading on screen.
     */
    protected string $auditLabelAttribute = 'name_ar';

    /**
     * Every leave filed under this type.
     *
     * Joined on `key`, not `id`: see the class note.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class, 'leave_type', 'key');
    }

    /**
     * The type keys whose approved days come off the yearly allowance.
     *
     * Every balance in the system asks this question, and before the catalogue
     * was a table each of them answered it with the literal string `annual`
     * written into a query. One place to ask means a company can mark a second
     * type as deducting - or none - and every screen agrees at once.
     *
     * Not memoised on purpose: the table holds a handful of rows, and a stale
     * cache here would quietly misreport people's balances.
     *
     * @return array<int, string>
     */
    public static function deductingKeys(): array
    {
        return static::query()
            ->where('deducts_from_allowance', true)
            ->pluck('key')
            ->all();
    }

    /**
     * The types a new request may be filed under.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Catalogue order: what the settings screen set, then oldest first so the
     * order never depends on two types sharing a rank.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
