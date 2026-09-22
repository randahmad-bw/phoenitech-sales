<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The landing dashboard aggregates company financials — contract values,
 * payments, revenue. It used to be open to any authenticated account; it is
 * now behind `dashboard.view`, which attendance-only staff do not hold.
 */
class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @test */
    public function staff_cannot_read_the_company_dashboard()
    {
        $this->actingAs($this->userWithRole('team'), 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error_code' => 'FORBIDDEN']);
    }

    /** @test */
    public function sales_and_management_can()
    {
        // "Only sales and management see the dashboard." Marketing is absent on
        // purpose: they produce the work rather than sell it, so the company's
        // financials are no more their business than they are the designers'.
        foreach (['sales', 'sales_manager', 'general_manager', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role), 'sanctum')
                ->getJson('/api/v1/dashboard')
                ->assertOk();
        }
    }

    /** @test */
    public function the_staff_role_holds_no_dashboard_permission()
    {
        $this->assertFalse(Role::findByName('team', 'web')->hasPermissionTo('dashboard.view'));

        foreach (['sales', 'sales_manager', 'general_manager', 'super_admin'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('dashboard.view'),
                "The {$role} role should still reach the dashboard."
            );
        }
    }

    /** @test */
    public function an_unauthenticated_request_is_still_rejected_first()
    {
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
    }
}
