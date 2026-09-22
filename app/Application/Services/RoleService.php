<?php

namespace App\Application\Services;

use App\Application\Support\AuditLogger;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Manages roles and their permission sets.
 *
 * The `super_admin` role is protected: it cannot be renamed or deleted, since
 * it is the un-lockable safety net (it bypasses checks via Gate::before).
 */
class RoleService
{
    /** Roles that may not be renamed or deleted from the dashboard. */
    private const PROTECTED_ROLES = ['super_admin'];

    public function list(): Collection
    {
        return $this->query()->with('permissions')->withCount('users')->orderBy('id')->get();
    }

    public function find(int $id): Role
    {
        return $this->query()->with('permissions')->withCount('users')->findOrFail($id);
    }

    /**
     * A Role query whose model already carries the stored guard.
     *
     * `withCount('users')` resolves spatie's `users()` relation from a *fresh*
     * Role instance, and that relation's target model is looked up from the
     * instance's `guard_name`. During a request the auth:sanctum middleware has
     * rewritten the default guard to "sanctum", which has no provider entry in
     * config/auth.php — so the relation would be built against a null model
     * class and blow up. Seeding the guard here keeps it pointed at User.
     */
    private function query(): Builder
    {
        return (new Role(['guard_name' => $this->guard()]))->newQuery();
    }

    /**
     * All permissions grouped by their prefix (e.g. "employees" => [...]),
     * for building the role editor UI.
     */
    public function permissionCatalog(): array
    {
        return Permission::orderBy('name')->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name)[0])
            ->map(fn ($names) => $names->values())
            ->toArray();
    }

    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $this->guard(),
        ]);

        $permissions = $data['permissions'] ?? [];
        $role->syncPermissions($this->resolvePermissions($permissions));
        $this->forgetCache();

        // Roles live in spatie's own model, which is not Auditable, so the trail
        // is written here instead of by a model event.
        AuditLogger::record(
            AuditLog::EVENT_CREATED,
            $role,
            null,
            ['name' => $role->name, 'permissions' => array_values($permissions)],
            $role->name,
        );

        return $role->load('permissions');
    }

    public function update(Role $role, array $data): Role
    {
        if (isset($data['name']) && $data['name'] !== $role->name && $this->isProtected($role)) {
            throw new BusinessRuleException("The '{$role->name}' role cannot be renamed.", 'ROLE_PROTECTED');
        }

        $previousName = $role->name;

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);

            if ($role->name !== $previousName) {
                AuditLogger::record(
                    AuditLog::EVENT_UPDATED,
                    $role,
                    ['name' => $previousName],
                    ['name' => $role->name],
                    $role->name,
                );
            }
        }

        if (array_key_exists('permissions', $data)) {
            $previousPermissions = $role->permissions->pluck('name')->sort()->values()->all();
            $role->syncPermissions($this->resolvePermissions($data['permissions']));

            $newPermissions = collect($data['permissions'])->sort()->values()->all();

            if ($previousPermissions !== $newPermissions) {
                AuditLogger::record(
                    AuditLog::EVENT_PERMISSIONS_CHANGED,
                    $role,
                    ['permissions' => $previousPermissions],
                    ['permissions' => $newPermissions],
                    $role->name,
                );
            }
        }

        $this->forgetCache();

        return $role->load('permissions');
    }

    /**
     * Resolve permission names to model instances under the configured guard.
     * Passing models (not strings) avoids spatie auto-detecting the request's
     * auth guard (e.g. "sanctum") instead of the guard the catalog was seeded
     * with ("web").
     */
    private function resolvePermissions(array $names): \Illuminate\Support\Collection
    {
        if (empty($names)) {
            return collect();
        }

        return Permission::whereIn('name', $names)
            ->where('guard_name', $this->guard())
            ->get();
    }

    /**
     * The guard the permission catalog lives under.
     *
     * NOT config('auth.defaults.guard'): the auth:sanctum middleware rewrites
     * that to "sanctum" during a request, whereas roles/permissions are stored
     * under the app guard ("web"). Spatie's resolver scans config.auth.guards
     * and returns that stable value, matching how the catalog was seeded.
     */
    private function guard(): string
    {
        return Guard::getDefaultName(User::class);
    }

    public function delete(Role $role): void
    {
        if ($this->isProtected($role)) {
            throw new BusinessRuleException("The '{$role->name}' role is a system role and cannot be deleted.", 'ROLE_PROTECTED');
        }

        if ($role->users()->exists()) {
            throw new BusinessRuleException(
                "The '{$role->name}' role is still assigned to users. Reassign them before deleting it.",
                'ROLE_IN_USE'
            );
        }

        $permissions = $role->permissions->pluck('name')->sort()->values()->all();
        $name = $role->name;

        $role->delete();
        $this->forgetCache();

        AuditLogger::record(
            AuditLog::EVENT_DELETED,
            $role,
            ['name' => $name, 'permissions' => $permissions],
            null,
            $name,
        );
    }

    private function isProtected(Role $role): bool
    {
        return in_array($role->name, self::PROTECTED_ROLES, true);
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
