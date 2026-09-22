<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('super_admin');
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

    /** @test */
    public function the_dashboard_leads_with_what_is_late()
    {
        $employee = Employee::factory()->create();
        Task::factory()->overdue()->create(['assigned_to' => $employee->id, 'title' => 'Late thing']);
        Task::factory()->create(['assigned_to' => $employee->id, 'due_date' => now()->addWeek()->toDateString()]);
        Task::factory()->done()->create(['assigned_to' => $employee->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('dashboard'))
            ->assertOk()
            ->assertJsonPath('data.tasks.scope', 'all')
            ->assertJsonPath('data.tasks.summary.overdue', 1)
            ->assertJsonPath('data.tasks.summary.done', 1);

        // Open work only, most pressing first: the finished task is not on it.
        $focus = $response->json('data.tasks.focus');
        $this->assertCount(2, $focus);
        $this->assertSame('Late thing', $focus[0]['title']);
        $this->assertTrue($focus[0]['is_overdue']);
    }

    /** @test */
    public function the_task_block_is_scoped_to_the_reader()
    {
        $mine = Employee::factory()->create();
        $reader = User::factory()->create();
        $reader->assignRole('sales');
        $mine->update(['user_id' => $reader->id]);

        Task::factory()->overdue()->create(['assigned_to' => $mine->id, 'title' => 'Mine and late']);
        Task::factory()->overdue()->create(['assigned_to' => Employee::factory()->create()->id]);

        $response = $this->actingAs($reader, 'sanctum')
            ->getJson(route('dashboard'))
            ->assertOk()
            // A salesperson holds `tasks.view_own`, so the numbers describe
            // their own plate — and say so.
            ->assertJsonPath('data.tasks.scope', 'own')
            ->assertJsonPath('data.tasks.summary.overdue', 1);

        $this->assertCount(1, $response->json('data.tasks.focus'));
        $this->assertSame('Mine and late', $response->json('data.tasks.focus.0.title'));
    }
}
