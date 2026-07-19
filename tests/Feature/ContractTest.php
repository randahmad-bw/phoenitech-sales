<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_list_contracts()
    {
        Contract::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('contracts.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    /** @test */
    public function authenticated_user_can_create_contract_with_auto_generated_number()
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create();
        $service = Service::factory()->create();

        $payload = [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'contract_value' => 15000.00,
            'currency' => 'USD',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'progress_percentage' => 10,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('contracts.store'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Contract created.',
            ]);

        $this->assertDatabaseHas('contracts', [
            'company_id' => $company->id,
            'contract_value' => 15000.00,
            'status' => 'draft',
        ]);

        $contract = Contract::first();
        $this->assertNotNull($contract->contract_number);
        $this->assertStringStartsWith('CNT-' . now()->year . '-', $contract->contract_number);
    }

    /** @test */
    public function authenticated_user_can_update_contract()
    {
        $contract = Contract::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson(route('contracts.update', $contract->id), [
                'status' => 'signed',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Contract updated.',
            ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => 'signed',
        ]);
    }

    /** @test */
    public function authenticated_user_can_delete_active_or_signed_contract()
    {
        $contract = Contract::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('contracts.destroy', $contract->id));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    }

    /** @test */
    public function authenticated_user_can_delete_draft_contract()
    {
        $contract = Contract::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('contracts.destroy', $contract->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Contract deleted.',
            ]);

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    }
}
