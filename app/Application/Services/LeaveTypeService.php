<?php

namespace App\Application\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The catalogue of leave types.
 *
 * Small, but it guards three things that are easy to get wrong:
 *
 * 1. **A type in use is never deleted.** `employee_leaves` stores the key as a
 *    plain string with no foreign key, precisely so history survives; deleting
 *    a used type would leave rows naming something that no longer exists, and
 *    every balance and report drawn from them would quietly change. Switching
 *    it off is the operation that was actually wanted.
 * 2. **The last active type cannot be switched off.** An empty catalogue means
 *    an empty picker, and nobody can request leave at all — a settings screen
 *    should not be able to break the module it configures.
 * 3. **`key` is never edited.** It is what existing records point at. The
 *    update path does not accept one, and create derives it rather than taking
 *    it from the client, so there is no way to send a key that collides with a
 *    record already on file.
 */
class LeaveTypeService
{
    /**
     * The catalogue, in order - switched-off types included.
     *
     * They are included because the client needs this list for two jobs at
     * once: filling the request picker, which filters on `is_active`, and
     * naming the type on leave already taken. A record filed under a type the
     * company has since stopped using must still read as that type rather than
     * fall back to a raw key. Nothing is granted by knowing the name: the write
     * paths validate against the active rows.
     *
     * @return Collection<int, LeaveType>
     */
    public function catalogue(): Collection
    {
        return LeaveType::query()->ordered()->get();
    }

    /**
     * The catalogue, each type carrying how many records use it.
     *
     * The count is what tells the settings screen whether a type can be
     * deleted or only switched off, so it is loaded here rather than left for
     * the screen to work out by fetching every leave record. It is management
     * information, which is why it is not on the list everyone receives.
     *
     * @return Collection<int, LeaveType>
     */
    public function withUsage(): Collection
    {
        return LeaveType::query()->withCount('leaves')->ordered()->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LeaveType
    {
        return LeaveType::create([
            'key' => $this->deriveKey($data['name_en']),
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'is_active' => $data['is_active'] ?? true,
            'deducts_from_allowance' => $data['deducts_from_allowance'] ?? false,
            'sort_order' => $data['sort_order'] ?? ((int) LeaveType::max('sort_order') + 1),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LeaveType $type, array $data): LeaveType
    {
        // `key` is not in the accepted set, but a payload that carries one
        // should not silently look as though it worked.
        unset($data['key']);

        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $this->guardLastActive($type);
        }

        $type->update($data);

        return $type->fresh();
    }

    public function delete(LeaveType $type): void
    {
        $used = $type->leaves()->count();

        if ($used > 0) {
            throw new BusinessRuleException(
                'This type is used by '.$used.' leave record(s), so deleting it would break them. Switch it off instead — it will stop appearing on the request form and the existing records stay as they are.',
                'LEAVE_TYPE_IN_USE'
            );
        }

        if ($type->is_active) {
            $this->guardLastActive($type);
        }

        $type->delete();
    }

    /**
     * Refuse to leave the catalogue with nothing in it.
     */
    private function guardLastActive(LeaveType $type): void
    {
        $others = LeaveType::query()->active()->whereKeyNot($type->getKey())->count();

        if ($others === 0) {
            throw new BusinessRuleException(
                'At least one leave type must stay active, otherwise nobody can request leave.',
                'LAST_ACTIVE_LEAVE_TYPE'
            );
        }
    }

    /**
     * A stable identifier derived from the English name.
     *
     * Derived rather than asked for: the key is an implementation detail of how
     * leave records point at a type, and a client that could choose one could
     * choose one already carrying history.
     */
    private function deriveKey(string $nameEn): string
    {
        $base = Str::slug($nameEn, '_') ?: 'type';
        $base = Str::limit($base, 36, '');
        $key = $base;
        $suffix = 2;

        while (LeaveType::where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}
