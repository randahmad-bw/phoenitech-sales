<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_list_employees()
    {
        Employee::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('employees.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /** @test */
    public function authenticated_user_can_create_employee()
    {
        $payload = [
            'name' => 'John Doe',
            'phone' => '1234567890',
            'email' => 'john@example.com',
            'employment_date' => '2026-01-01',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('employees.store'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Employee created.',
            ]);

        $this->assertDatabaseHas('employees', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    /** @test */
    public function authenticated_user_can_show_employee()
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('employees.show', $employee->id));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'phone', 'email', 'employment_date'],
            ]);
    }

    /** @test */
    public function authenticated_user_can_update_employee()
    {
        $employee = Employee::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson(route('employees.update', $employee->id), [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employee updated.',
            ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'New Name',
        ]);
    }

    /** @test */
    public function authenticated_user_can_delete_employee_without_active_contracts()
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('employees.destroy', $employee->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employee deleted.',
            ]);

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    /** @test */
    public function authenticated_user_cannot_delete_employee_with_active_contracts()
    {
        $employee = Employee::factory()->create();
        Contract::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('employees.destroy', $employee->id));

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'EMPLOYEE_HAS_ACTIVE_CONTRACTS',
            ]);
    }

    /** @test */
    public function authenticated_user_can_get_employee_stats()
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('employees.stats', $employee->id));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_companies',
                    'total_contracts',
                    'total_value',
                    'total_paid',
                    'remaining',
                    'avg_value',
                ],
            ]);
    }
}
