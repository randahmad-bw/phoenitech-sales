<?php

namespace App\Application\Services;

use App\Application\Support\AuditLogger;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Admin-facing user management: CRUD, role assignment, activation, and
 * password reset.
 *
 * Guards protect against foot-guns that would lock the system:
 *  - a user cannot delete or disable their own account;
 *  - the last active super_admin cannot be deleted, disabled, or demoted.
 */
class UserManagementService
{
    private const SUPER_ADMIN = 'super_admin';

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = User::with('roles')->withCount('roles');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters['role']));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->paginate($filters['per_page'] ?? 25);
    }

    public function find(int $id): User
    {
        return User::with(['roles', 'employee'])->findOrFail($id);
    }

    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'], // hashed by the model cast
            'is_active' => $data['is_active'] ?? true,
            'must_change_password' => $data['must_change_password'] ?? false,
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
            $this->auditRoleChange($user, [], $data['roles']);
        }

        return $user->load('roles');
    }

    public function update(User $user, array $data): User
    {
        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'username' => array_key_exists('username', $data) ? $data['username'] : null,
            'email' => $data['email'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('is_active', $data)) {
            $this->guardActiveChange($user, (bool) $data['is_active']);
            $user->is_active = (bool) $data['is_active'];
        }

        $user->save();

        if (array_key_exists('roles', $data)) {
            $this->guardRoleChange($user, $data['roles']);
            $previousRoles = $user->roles->pluck('name')->all();
            $user->syncRoles($data['roles']);
            $this->auditRoleChange($user, $previousRoles, $data['roles']);
        }

        return $user->load('roles');
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        if (! $active && $user->id === $actor->id) {
            throw new BusinessRuleException('You cannot disable your own account.', 'SELF_DISABLE');
        }

        $this->guardActiveChange($user, $active);

        $user->update(['is_active' => $active]);

        // A disabled account's existing tokens should stop working immediately.
        if (! $active) {
            $user->tokens()->delete();
        }

        return $user->load('roles');
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password' => $newPassword, // hashed by the model cast
            'must_change_password' => true,
        ])->save();

        // Force re-login everywhere with the new credentials.
        $user->tokens()->delete();

        // The new password is never written to the trail — only who reset whose.
        AuditLogger::record(AuditLog::EVENT_PASSWORD_RESET, $user);
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw new BusinessRuleException('You cannot delete your own account.', 'SELF_DELETE');
        }

        if ($user->hasRole(self::SUPER_ADMIN) && $this->activeSuperAdminCount() <= 1) {
            throw new BusinessRuleException('You cannot delete the last super admin.', 'LAST_SUPER_ADMIN');
        }

        $user->tokens()->delete();
        $user->delete();
    }

    /**
     * Record a role assignment in the audit trail.
     *
     * Role changes go through spatie's pivot tables, which fire no model events,
     * so the Auditable trait cannot see them — they are logged explicitly here.
     */
    private function auditRoleChange(User $user, array $previous, array $current): void
    {
        sort($previous);
        sort($current);

        if ($previous === $current) {
            return;
        }

        AuditLogger::record(
            AuditLog::EVENT_ROLES_CHANGED,
            $user,
            ['roles' => $previous],
            ['roles' => $current],
        );
    }

    /**
     * Block any change that would remove the final active super admin.
     */
    private function guardActiveChange(User $user, bool $active): void
    {
        if (! $active && $user->hasRole(self::SUPER_ADMIN) && $this->activeSuperAdminCount() <= 1) {
            throw new BusinessRuleException('You cannot disable the last active super admin.', 'LAST_SUPER_ADMIN');
        }
    }

    /**
     * Block demoting the final super admin out of the role.
     */
    private function guardRoleChange(User $user, array $newRoles): void
    {
        $losingSuperAdmin = $user->hasRole(self::SUPER_ADMIN) && ! in_array(self::SUPER_ADMIN, $newRoles, true);

        if ($losingSuperAdmin && $this->activeSuperAdminCount() <= 1) {
            throw new BusinessRuleException('You cannot remove the super admin role from the last super admin.', 'LAST_SUPER_ADMIN');
        }
    }

    private function activeSuperAdminCount(): int
    {
        return User::role(self::SUPER_ADMIN)->where('is_active', true)->count();
    }
}
