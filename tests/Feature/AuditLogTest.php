<?php

namespace Tests\Feature;

use App\Application\Support\AuditLogger;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The audit trail: model changes, auth events, role changes, the read API and
 * its authorization, and retention pruning.
 */
class AuditLogTest extends TestCase
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

    // ─── Model events ────────────────────────────────────────────

    /** @test */
    public function creating_a_record_is_logged_with_the_acting_user()
    {
        $actor = $this->userWithRole('super_admin');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/companies', ['name' => 'Acme Ltd'])
            ->assertStatus(201);

        $entry = AuditLog::where('auditable_type', Company::class)
            ->where('event', AuditLog::EVENT_CREATED)
            ->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertSame($actor->id, $entry->user_id);
        $this->assertSame($actor->name, $entry->user_name);
        $this->assertSame('Acme Ltd', $entry->auditable_label);
        $this->assertSame('Acme Ltd', $entry->new_values['name']);
        $this->assertNull($entry->old_values);
    }

    /** @test */
    public function updating_a_record_logs_only_the_changed_fields()
    {
        $actor = $this->userWithRole('super_admin');
        $company = Company::factory()->create(['name' => 'Before', 'phone' => '111']);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/companies/{$company->id}", ['name' => 'After', 'phone' => '111'])
            ->assertStatus(200);

        $entry = AuditLog::where('event', AuditLog::EVENT_UPDATED)
            ->where('auditable_id', $company->id)
            ->latest('id')->firstOrFail();

        $this->assertSame(['name' => 'Before'], $entry->old_values);
        $this->assertSame(['name' => 'After'], $entry->new_values);
        // The unchanged column is absent from the diff.
        $this->assertArrayNotHasKey('phone', $entry->new_values);
    }

    /** @test */
    public function a_save_that_changes_nothing_writes_no_entry()
    {
        $company = Company::factory()->create(['name' => 'Same']);

        $before = AuditLog::count();
        $company->update(['name' => 'Same']);

        $this->assertSame($before, AuditLog::count());
    }

    /** @test */
    public function deleting_a_record_keeps_its_label_and_values()
    {
        $actor = $this->userWithRole('super_admin');
        $company = Company::factory()->create(['name' => 'Doomed Co']);

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/companies/{$company->id}")
            ->assertStatus(200);

        $entry = AuditLog::where('event', AuditLog::EVENT_DELETED)
            ->where('auditable_id', $company->id)
            ->firstOrFail();

        $this->assertSame('Doomed Co', $entry->auditable_label);
        $this->assertSame('Doomed Co', $entry->old_values['name']);
        $this->assertNull($entry->new_values);
    }

    /** @test */
    public function passwords_are_never_written_into_the_trail()
    {
        $this->actingAs($this->userWithRole('super_admin'), 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Secretive',
                'email' => 'secretive@phoenitech.sy',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
            ])->assertStatus(201);

        $entry = AuditLog::where('auditable_type', User::class)
            ->where('event', AuditLog::EVENT_CREATED)
            ->latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('password', $entry->new_values);
        $this->assertStringNotContainsString('Password123', json_encode($entry->new_values));
    }

    // ─── Auth events ─────────────────────────────────────────────

    /** @test */
    public function a_successful_login_is_logged()
    {
        $user = User::factory()->create(['email' => 'log@phoenitech.sy', 'password' => 'Password123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'log@phoenitech.sy',
            'password' => 'Password123',
        ])->assertStatus(200);

        $entry = AuditLog::where('event', AuditLog::EVENT_LOGIN)->firstOrFail();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame('email', $entry->new_values['via']);
    }

    /** @test */
    public function a_failed_login_is_logged_with_the_attempted_identifier()
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@phoenitech.sy',
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $entry = AuditLog::where('event', AuditLog::EVENT_LOGIN_FAILED)->firstOrFail();

        $this->assertNull($entry->user_id);
        $this->assertSame('ghost@phoenitech.sy', $entry->new_values['identifier']);
        $this->assertSame('unknown_identifier', $entry->new_values['reason']);
    }

    /** @test */
    public function a_blocked_login_by_a_disabled_account_is_logged()
    {
        User::factory()->create([
            'email' => 'disabled@phoenitech.sy',
            'password' => 'Password123',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled@phoenitech.sy',
            'password' => 'Password123',
        ])->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', ['event' => AuditLog::EVENT_LOGIN_BLOCKED]);
    }

    /** @test */
    public function changing_your_own_password_is_logged_without_the_password()
    {
        $user = User::factory()->create(['password' => 'Password123']);
        // A real token, because change-password reads currentAccessToken().
        $token = $user->createToken('current')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'Password123',
                'password' => 'NewPassword456',
                'password_confirmation' => 'NewPassword456',
            ])->assertStatus(200);

        $entry = AuditLog::where('event', AuditLog::EVENT_PASSWORD_CHANGED)->firstOrFail();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertNull($entry->new_values);
    }

    /** @test */
    public function an_admin_password_reset_records_both_the_actor_and_the_target()
    {
        $admin = $this->userWithRole('super_admin');
        $target = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/users/{$target->id}/reset-password", [
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
            ])->assertStatus(200);

        $entry = AuditLog::where('event', AuditLog::EVENT_PASSWORD_RESET)->firstOrFail();

        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame($target->id, (int) $entry->auditable_id);
    }

    // ─── Role & permission changes ───────────────────────────────

    /** @test */
    public function changing_a_users_roles_is_logged()
    {
        $admin = $this->userWithRole('super_admin');
        $target = $this->userWithRole('employee');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/users/{$target->id}", ['roles' => ['manager']])
            ->assertStatus(200);

        $entry = AuditLog::where('event', AuditLog::EVENT_ROLES_CHANGED)
            ->where('auditable_id', $target->id)
            ->latest('id')->firstOrFail();

        $this->assertSame(['employee'], $entry->old_values['roles']);
        $this->assertSame(['manager'], $entry->new_values['roles']);
    }

    /** @test */
    public function changing_a_roles_permissions_is_logged()
    {
        $admin = $this->userWithRole('super_admin');
        $role = \Spatie\Permission\Models\Role::findByName('employee');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['companies.view']])
            ->assertStatus(200);

        $entry = AuditLog::where('event', AuditLog::EVENT_PERMISSIONS_CHANGED)->latest('id')->firstOrFail();

        $this->assertSame(['companies.view'], $entry->new_values['permissions']);
        $this->assertContains('employees.view_own', $entry->old_values['permissions']);
    }

    // ─── Read API ────────────────────────────────────────────────

    /** @test */
    public function an_authorized_user_can_read_the_trail()
    {
        $actor = $this->userWithRole('super_admin');
        Company::factory()->create(['name' => 'Listed Co']);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(200)
            ->assertJsonPath('data.0.auditable.type', 'company')
            ->assertJsonPath('data.0.auditable.label', 'Listed Co')
            ->assertJsonPath('data.0.event', 'created')
            ->assertJsonStructure(['data' => [['id', 'event', 'user', 'auditable', 'changes', 'created_at']], 'meta']);
    }

    /** @test */
    public function the_trail_is_hidden_from_users_without_audit_view()
    {
        $this->actingAs($this->userWithRole('manager'), 'sanctum')
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'FORBIDDEN']);
    }

    /** @test */
    public function the_trail_can_be_filtered_by_event_and_type()
    {
        $actor = $this->userWithRole('super_admin');
        $company = Company::factory()->create();
        $company->update(['name' => 'Renamed']);
        Contract::factory()->create();

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/audit-logs?event=updated&type=company')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'updated');

        // An unknown type filter matches nothing instead of widening the result.
        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/audit-logs?type=not_a_model')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function the_trail_for_a_single_record_can_be_fetched()
    {
        $actor = $this->userWithRole('super_admin');
        $company = Company::factory()->create();
        $company->update(['name' => 'Second Name']);

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/v1/audit-logs/record/company/{$company->id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function the_filter_options_list_only_what_occurs_in_the_trail()
    {
        $actor = $this->userWithRole('super_admin');
        $company = Company::factory()->create();

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/companies/{$company->id}", ['name' => 'Renamed Co'])
            ->assertStatus(200);

        $response = $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/audit-logs/filters')
            ->assertStatus(200)
            ->json('data');

        // Only events and types that actually occur — nothing is deleted yet,
        // and no auth event has happened in this test.
        $this->assertEqualsCanonicalizing(['created', 'updated'], $response['events']);
        $this->assertContains('company', $response['types']);
        $this->assertNotContains('contract', $response['types']);
        $this->assertContains($actor->id, array_column($response['users'], 'id'));
    }

    /** @test */
    public function an_entry_keeps_the_actors_name_after_the_account_is_deleted()
    {
        $actor = $this->userWithRole('super_admin', ['name' => 'Departed Admin']);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/v1/companies', ['name' => 'Legacy Co'])
            ->assertStatus(201);

        $actor->delete();

        $entry = AuditLog::where('auditable_type', Company::class)->latest('id')->firstOrFail();

        $this->assertNull($entry->fresh()->user_id);
        $this->assertSame('Departed Admin', $entry->user_name);
    }

    // ─── Switches & retention ────────────────────────────────────

    /** @test */
    public function auditing_can_be_switched_off_for_a_callback()
    {
        AuditLogger::withoutAuditing(function () {
            Company::factory()->create(['name' => 'Silent Co']);
        });

        $this->assertDatabaseMissing('audit_logs', ['auditable_label' => 'Silent Co']);
        // The switch is restored afterwards.
        Company::factory()->create(['name' => 'Loud Co']);
        $this->assertDatabaseHas('audit_logs', ['auditable_label' => 'Loud Co']);
    }

    /** @test */
    public function pruning_removes_only_entries_past_the_retention_window()
    {
        Company::factory()->create(['name' => 'Recent Co']);
        $old = AuditLog::create([
            'event' => AuditLog::EVENT_CREATED,
            'created_at' => now()->subDays(400),
        ]);

        $this->artisan('audit:prune', ['--days' => 365])->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_label' => 'Recent Co']);
    }
}
