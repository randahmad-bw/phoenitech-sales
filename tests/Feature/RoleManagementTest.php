<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin role-management API: CRUD, permission sync, catalog, and the guards
 * protecting system roles and roles still in use.
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    /** @test */
    public function it_returns_the_grouped_permission_catalog()
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/permissions')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['users', 'employees', 'contracts', 'attendance']]);
    }

    /** @test */
    public function an_admin_can_create_a_role_with_permissions()
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/roles', [
                'name' => 'accountant',
                'permissions' => ['payments.view', 'reports.view', 'reports.export'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'accountant')
            ->assertJsonCount(3, 'data.permissions');
    }

    /** @test */
    public function an_admin_can_update_a_roles_permissions()
    {
        $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['settings.view']])
            ->assertStatus(200)
            ->assertJsonPath('data.permissions.0', 'settings.view');
    }

    /** @test */
    public function an_unused_role_can_be_deleted()
    {
        $role = Role::create(['name' => 'disposable', 'guard_name' => 'web']);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('roles', ['name' => 'disposable']);
    }

    /** @test */
    public function the_super_admin_role_is_protected_from_deletion()
    {
        $role = Role::findByName('super_admin');

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(409)
            ->assertJson(['error_code' => 'ROLE_PROTECTED']);
    }

    /** @test */
    public function a_role_still_assigned_to_users_cannot_be_deleted()
    {
        $role = Role::create(['name' => 'in_use', 'guard_name' => 'web']);
        $holder = User::factory()->create();
        $holder->assignRole('in_use');

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(409)
            ->assertJson(['error_code' => 'ROLE_IN_USE']);
    }
}
