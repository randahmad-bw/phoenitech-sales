<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guards the shape of the role set itself.
 *
 * The set used to answer two questions at once — what an account may reach, and
 * what the person does for a living — and the two answers contradicted each
 * other. `employee` was the sales role while every designer was `staff`, so the
 * word "employee" meant the sales team here and the whole company in the
 * employees table. `admin` and `super_admin` held identical permissions, and
 * `hr` held 24 permissions and no users.
 *
 * These tests exist so the set cannot quietly drift back. They assert the
 * boundaries that carry meaning, not the exact permission counts, which are
 * meant to be edited from the admin dashboard.
 */
class RoleStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** @test */
    public function the_role_set_is_exactly_the_six_levels_of_access()
    {
        $this->assertSame(
            ['general_manager', 'marketing', 'sales', 'sales_manager', 'super_admin', 'team'],
            Role::where('guard_name', 'web')->orderBy('name')->pluck('name')->all(),
        );
    }

    /** @test */
    public function the_replaced_roles_are_gone()
    {
        foreach (['admin', 'hr', 'manager', 'employee', 'staff'] as $legacy) {
            $this->assertFalse(
                Role::where('name', $legacy)->where('guard_name', 'web')->exists(),
                "The `{$legacy}` role should no longer exist.",
            );
        }
    }

    /** @test */
    public function reseeding_carries_a_users_access_across_the_rename()
    {
        // The whole risk of the rename: spatie keys assignments by role id, so
        // renaming the row keeps them — but only if the seeder renames rather
        // than creating a new role beside the old one.
        $role = Role::findOrCreate('staff', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->seed(RolePermissionSeeder::class);

        $this->assertTrue($user->fresh()->hasRole('team'));
        $this->assertFalse(Role::where('name', 'staff')->exists());
    }

    /** @test */
    public function the_general_manager_runs_everything_except_the_accounts()
    {
        $gm = Role::findByName('general_manager', 'web');

        // Sees and runs the work.
        foreach (['contracts.view_all', 'attendance.manage_schedules', 'audit.view', 'reports.export'] as $permission) {
            $this->assertTrue($gm->hasPermissionTo($permission), "general_manager should hold {$permission}.");
        }

        // But cannot create logins or widen anyone's access, including their own.
        foreach (['users.create', 'users.delete', 'roles.edit', 'roles.assign_permissions'] as $permission) {
            $this->assertFalse($gm->hasPermissionTo($permission), "general_manager must not hold {$permission}.");
        }
    }

    /** @test */
    public function only_the_super_admin_manages_accounts()
    {
        foreach (Role::where('guard_name', 'web')->where('name', '!=', 'super_admin')->get() as $role) {
            foreach (['users.create', 'users.delete', 'roles.assign_permissions'] as $permission) {
                $this->assertFalse(
                    $role->hasPermissionTo($permission),
                    "The `{$role->name}` role must not hold {$permission}.",
                );
            }
        }
    }

    /** @test */
    public function the_production_team_sees_nothing_commercial()
    {
        $team = Role::findByName('team', 'web');

        // The original requirement: contracts belong to sales and management.
        // The dashboard is company financials, which is the same boundary.
        foreach (['contracts.view_own', 'contracts.view_all', 'dashboard.view', 'companies.view', 'payments.view'] as $permission) {
            $this->assertFalse($team->hasPermissionTo($permission), "team must not hold {$permission}.");
        }

        // What they do need: their hours, their leave, their own profile.
        foreach (['attendance.view_own', 'attendance.create', 'leave.create', 'employees.view_own'] as $permission) {
            $this->assertTrue($team->hasPermissionTo($permission), "team should hold {$permission}.");
        }
    }

    /** @test */
    public function a_department_outside_the_list_is_rejected()
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        // `sales_team` looks plausible and is not a department. Before the list
        // existed it would have been stored as-is and then matched no filter on
        // any screen, quietly inventing an eighth department.
        $this->actingAs($actor, 'sanctum')
            ->postJson(route('employees.store'), [
                'name' => 'Someone',
                'department' => 'sales_team',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('department');
    }

    /** @test */
    public function every_listed_department_is_accepted()
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        foreach (Employee::DEPARTMENTS as $index => $department) {
            $this->actingAs($actor, 'sanctum')
                ->postJson(route('employees.store'), [
                    'name' => "Person {$index}",
                    'department' => $department,
                ])
                ->assertStatus(201)
                ->assertJsonPath('data.department', $department);
        }
    }

    /** @test */
    public function the_employees_listing_hides_the_account_block_from_those_who_may_not_see_accounts()
    {
        // The team screen shows each person's role beside their department, so
        // the employees endpoint now carries the linked login. That must not
        // become a side door: someone who cannot open the accounts screen
        // should not learn a colleague's role or login state from here either.
        $viewer = User::factory()->create();
        $viewer->assignRole('team');
        Employee::factory()->create(['user_id' => $viewer->id, 'department' => 'design']);

        $this->actingAs($viewer, 'sanctum')
            ->getJson(route('employees.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.account');
    }

    /** @test */
    public function the_employees_listing_carries_the_account_for_someone_who_may_see_accounts()
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('super_admin');

        $person = User::factory()->create();
        $person->assignRole('sales');
        Employee::factory()->create(['user_id' => $person->id, 'department' => 'sales']);

        $this->actingAs($viewer, 'sanctum')
            ->getJson(route('employees.index'))
            ->assertOk()
            ->assertJsonPath('data.0.account.roles.0', 'sales')
            ->assertJsonPath('data.0.account.is_active', true);
    }

    /** @test */
    public function accounts_with_no_employee_can_be_listed_on_their_own()
    {
        // The team screen's footnote section. Without the filter these accounts
        // are invisible on a screen that lists people.
        $viewer = User::factory()->create();
        $viewer->assignRole('super_admin');

        $orphan = User::factory()->create(['name' => 'Technical Account']);
        $linked = User::factory()->create();
        Employee::factory()->create(['user_id' => $linked->id]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson(route('users.index', ['without_employee' => 1]))
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($orphan->id, $ids);
        $this->assertNotContains($linked->id, $ids);
    }
}
