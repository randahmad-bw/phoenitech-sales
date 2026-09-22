<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves authorization is enforced at the API layer (not just hidden in the UI):
 * direct requests from under-privileged accounts are rejected by the backend.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /** @test */
    public function employee_cannot_delete_an_employee()
    {
        $actor = $this->userWithRole('sales');
        $target = Employee::factory()->create();

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/employees/{$target->id}")
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error_code' => 'FORBIDDEN']);
    }

    /** @test */
    public function employee_cannot_create_a_contract()
    {
        $actor = $this->userWithRole('sales');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/contracts', [])
            ->assertStatus(403);
    }

    /** @test */
    public function a_mid_level_role_cannot_delete_an_employee_but_super_admin_can()
    {
        // The sales manager is senior and still has no employees.delete. The
        // general manager would be the wrong actor here: it holds every
        // operational permission, so it proves nothing about the boundary.
        $manager = $this->userWithRole('sales_manager');
        $target = Employee::factory()->create();

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/employees/{$target->id}")
            ->assertStatus(403);
    }

    /** @test */
    public function super_admin_passes_every_permission_check()
    {
        $admin = $this->userWithRole('super_admin');
        $target = Employee::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/employees/{$target->id}")
            ->assertStatus(200);
    }

    /** @test */
    public function employee_listing_is_scoped_to_their_own_profile()
    {
        $actor = $this->userWithRole('sales');
        $ownProfile = Employee::factory()->create(['user_id' => $actor->id]);
        Employee::factory()->count(3)->create(); // other employees

        $response = $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/employees')
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($ownProfile->id, $data[0]['id']);
    }

    /** @test */
    public function a_granted_permission_actually_lets_a_non_admin_through()
    {
        // Guards against the sanctum-vs-web guard pitfall: a manager holds
        // contracts.view_all, so the contracts listing must return 200, not 403.
        $manager = $this->userWithRole('general_manager');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/contracts')
            ->assertStatus(200);
    }

    /** @test */
    public function a_disabled_account_is_blocked_from_protected_routes()
    {
        $user = $this->userWithRole('super_admin', ['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertStatus(403)
            ->assertJson(['message' => 'Your account has been disabled. Please contact an administrator.']);
    }

    /** @test */
    public function auth_me_exposes_roles_and_permissions()
    {
        $manager = $this->userWithRole('general_manager');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.roles.0', 'general_manager')
            ->assertJsonStructure(['data' => ['roles', 'permissions', 'is_active']]);
    }
}
