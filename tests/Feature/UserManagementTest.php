<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin user-management API: CRUD, roles, activation, password reset, and the
 * safety guards that prevent locking the system.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actor(string $role = 'super_admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @test */
    public function an_admin_can_create_a_user_with_a_role()
    {
        $response = $this->actingAs($this->actor(), 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'New Person',
                'email' => 'new@phoenitech.sy',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'roles' => ['manager'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.roles.0', 'manager');

        $this->assertDatabaseHas('users', ['email' => 'new@phoenitech.sy']);
    }

    /** @test */
    public function it_rejects_a_duplicate_email()
    {
        User::factory()->create(['email' => 'dupe@phoenitech.sy']);

        $this->actingAs($this->actor(), 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'X', 'email' => 'dupe@phoenitech.sy',
                'password' => 'Password123', 'password_confirmation' => 'Password123',
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function an_admin_can_update_a_users_roles()
    {
        $target = User::factory()->create();
        $target->assignRole('employee');

        $this->actingAs($this->actor(), 'sanctum')
            ->putJson("/api/v1/users/{$target->id}", ['roles' => ['hr']])
            ->assertStatus(200)
            ->assertJsonPath('data.roles.0', 'hr');

        $this->assertTrue($target->fresh()->hasRole('hr'));
        $this->assertFalse($target->fresh()->hasRole('employee'));
    }

    /** @test */
    public function disabling_a_user_revokes_their_tokens()
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->createToken('t');

        $this->actingAs($this->actor(), 'sanctum')
            ->patchJson("/api/v1/users/{$target->id}/status", ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function resetting_a_password_forces_a_change_and_revokes_tokens()
    {
        $target = User::factory()->create(['must_change_password' => false]);
        $target->createToken('t');

        $this->actingAs($this->actor(), 'sanctum')
            ->postJson("/api/v1/users/{$target->id}/reset-password", [
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
            ])
            ->assertStatus(200);

        $this->assertTrue($target->fresh()->must_change_password);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function a_user_cannot_delete_their_own_account()
    {
        $actor = $this->actor();

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/users/{$actor->id}")
            ->assertStatus(409)
            ->assertJson(['error_code' => 'SELF_DELETE']);
    }

    /** @test */
    public function the_last_super_admin_cannot_be_deleted()
    {
        // Actor is an admin (has users.delete) but not the super admin.
        $actor = $this->actor('admin');
        $onlySuperAdmin = User::factory()->create();
        $onlySuperAdmin->assignRole('super_admin');

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/users/{$onlySuperAdmin->id}")
            ->assertStatus(409)
            ->assertJson(['error_code' => 'LAST_SUPER_ADMIN']);
    }

    /** @test */
    public function the_last_super_admin_cannot_be_disabled()
    {
        $actor = $this->actor('admin');
        $onlySuperAdmin = User::factory()->create(['is_active' => true]);
        $onlySuperAdmin->assignRole('super_admin');

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/v1/users/{$onlySuperAdmin->id}/status", ['is_active' => false])
            ->assertStatus(409)
            ->assertJson(['error_code' => 'LAST_SUPER_ADMIN']);
    }

    /** @test */
    public function a_user_without_users_permission_is_forbidden()
    {
        // manager has no users.* permissions.
        $this->actingAs($this->actor('manager'), 'sanctum')
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }
}
