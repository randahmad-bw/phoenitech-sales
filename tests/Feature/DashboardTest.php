<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_retrieve_dashboard_overview_stats()
    {
        // Seed some data
        Company::factory()->count(2)->create();
        Contract::factory()->count(3)->create(['contract_value' => 5000]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('dashboard'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'stats' => [
                        'total_companies',
                        'total_contacts',
                        'total_contracts',
                        'active_contracts',
                        'completed_contracts',
                        'cancelled_contracts',
                        'total_contract_value',
                        'total_paid',
                        'total_remaining',
                        'collection_percentage',
                        'avg_contract_value',
                        'largest_contract',
                    ],
                    'charts' => [
                        'monthly_sales',
                        'monthly_collections',
                        'contracts_by_status',
                        'top_employees',
                        'top_services',
                        'year_comparison',
                    ],
                ],
            ]);
    }
}
