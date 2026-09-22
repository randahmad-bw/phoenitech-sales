<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catalogue of leave types.
 *
 * It used to be a PHP constant, a database enum and a TypeScript union at once,
 * so a company could neither add a type nor stop offering one. These tests pin
 * the three rules that make it safe to edit from a settings screen: a type with
 * history is never deleted, the catalogue is never emptied, and the key every
 * leave record points at is never rewritten.
 */
class LeaveTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employeeUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Holds settings.view / settings.edit, but not users.* or roles.*.
        $this->admin = User::factory()->create();
        $this->admin->assignRole('general_manager');

        $this->employeeUser = User::factory()->create();
        $this->employeeUser->assignRole('team');
        $this->employee = Employee::factory()->create(['user_id' => $this->employeeUser->id]);
    }

    // --- The catalogue exists everywhere ---

    public function test_the_five_original_types_are_seeded_by_the_migration(): void
    {
        // Seeded in the migration rather than a seeder: the request form cannot
        // render without a type, so no environment may ever be without them.
        $this->assertSame(5, LeaveType::count());
        $this->assertTrue((bool) LeaveType::where('key', 'annual')->value('deducts_from_allowance'));
        $this->assertFalse((bool) LeaveType::where('key', 'sick')->value('deducts_from_allowance'));
    }

    // --- Reading ---

    public function test_the_catalogue_reaches_anyone_who_may_request_leave_flagged_not_filtered(): void
    {
        LeaveType::where('key', 'special')->update(['is_active' => false]);

        $rows = collect(
            $this->actingAs($this->employeeUser, 'sanctum')
                ->getJson('/api/v1/leave-types')
                ->assertOk()
                ->json('data')
        )->keyBy('key');

        // A switched-off type stays in the payload so leave already filed under
        // it still reads as that type; the picker is what drops it.
        $this->assertTrue($rows->has('special'));
        $this->assertFalse($rows['special']['is_active']);
        $this->assertTrue($rows['annual']['is_active']);

        // Usage counts are management information and are not handed out here.
        $this->assertArrayNotHasKey('leaves_count', $rows['annual']);
    }

    public function test_the_management_listing_includes_switched_off_types_and_usage_counts(): void
    {
        LeaveType::where('key', 'special')->update(['is_active' => false]);
        $this->leaveOfType('annual');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/leave-types/manage')
            ->assertOk();

        $rows = collect($response->json('data'))->keyBy('key');

        $this->assertTrue($rows->has('special'));
        $this->assertSame(1, $rows['annual']['leaves_count']);
        $this->assertSame(0, $rows['sick']['leaves_count']);
    }

    public function test_an_employee_cannot_reach_the_management_listing(): void
    {
        $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/leave-types/manage')
            ->assertForbidden();
    }

    // --- Writing ---

    public function test_a_type_can_be_added_and_its_key_is_derived_from_the_english_name(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/leave-types', [
                'name_ar' => 'اجازة امومة',
                'name_en' => 'Maternity Leave',
                'deducts_from_allowance' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'maternity_leave')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_a_derived_key_never_collides_with_one_already_on_file(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/leave-types', ['name_ar' => 'سنوية', 'name_en' => 'Annual'])
            ->assertCreated()
            // `annual` is taken by a type that already carries history.
            ->assertJsonPath('data.key', 'annual_2');
    }

    public function test_an_employee_cannot_add_a_type(): void
    {
        $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/leave-types', ['name_ar' => 'x', 'name_en' => 'x'])
            ->assertForbidden();
    }

    public function test_renaming_a_type_leaves_its_key_alone(): void
    {
        $type = LeaveType::where('key', 'special')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/leave-types/{$type->id}", [
                'name_ar' => 'مناسبات',
                'name_en' => 'Occasions',
                // Sent deliberately: it must be ignored, not applied.
                'key' => 'occasions',
            ])
            ->assertOk()
            ->assertJsonPath('data.key', 'special')
            ->assertJsonPath('data.name_ar', 'مناسبات');
    }

    // --- The guards ---

    public function test_a_type_that_is_in_use_cannot_be_deleted(): void
    {
        $this->leaveOfType('sick');
        $type = LeaveType::where('key', 'sick')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/leave-types/{$type->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'LEAVE_TYPE_IN_USE');

        $this->assertDatabaseHas('leave_types', ['key' => 'sick']);
    }

    public function test_an_unused_type_can_be_deleted(): void
    {
        $type = LeaveType::where('key', 'special')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/leave-types/{$type->id}")
            ->assertOk();

        $this->assertDatabaseMissing('leave_types', ['key' => 'special']);
    }

    public function test_a_used_type_can_still_be_switched_off(): void
    {
        $this->leaveOfType('sick');
        $type = LeaveType::where('key', 'sick')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/leave-types/{$type->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Switching off hides the type from the form; it does not touch the
        // records already filed under it.
        $this->assertDatabaseHas('employee_leaves', ['leave_type' => 'sick']);
    }

    public function test_the_last_active_type_cannot_be_switched_off(): void
    {
        LeaveType::where('key', '!=', 'annual')->update(['is_active' => false]);
        $last = LeaveType::where('key', 'annual')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/leave-types/{$last->id}", ['is_active' => false])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'LAST_ACTIVE_LEAVE_TYPE');
    }

    // --- What the catalogue controls ---

    public function test_a_request_cannot_be_filed_under_a_switched_off_type(): void
    {
        LeaveType::where('key', 'special')->update(['is_active' => false]);

        $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/leaves/my', [
                'leave_type' => 'special',
                'start_date' => '2026-10-04',
                'end_date' => '2026-10-05',
                'reason' => 'Family event.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('leave_type');
    }

    public function test_the_balance_follows_the_deducts_flag_rather_than_the_word_annual(): void
    {
        // Sick leave starts free and annual costs. Swap them over: the balance
        // must follow the flag, not the name.
        LeaveType::where('key', 'annual')->update(['deducts_from_allowance' => false]);
        LeaveType::where('key', 'sick')->update(['deducts_from_allowance' => true]);

        $this->employee->update(['annual_leave_allowance' => 20]);
        $this->leaveOfType('annual', 3);
        $this->leaveOfType('sick', 2);

        $balance = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/leaves/my')
            ->assertOk()
            ->json('data.balance');

        $this->assertSame(2.0, (float) $balance['annual_used']);
        $this->assertSame(18.0, (float) $balance['annual_remaining']);

        $byType = collect($balance['by_type'])->keyBy('key');
        $this->assertSame(3.0, (float) $byType['annual']['days']);
        $this->assertSame(2.0, (float) $byType['sick']['days']);
    }

    /**
     * An approved leave record of a given type.
     */
    private function leaveOfType(string $key, float $days = 1.0): EmployeeLeave
    {
        return EmployeeLeave::create([
            'employee_id' => $this->employee->id,
            'leave_type' => $key,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'days_count' => $days,
            'status' => EmployeeLeave::STATUS_APPROVED,
            'reason' => 'Test record.',
        ]);
    }
}
