<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_list_companies()
    {
        Company::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('companies.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    /** @test */
    public function authenticated_user_can_filter_companies_by_employee()
    {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        Company::factory()->create(['employee_id' => $employee1->id]);
        Company::factory()->create(['employee_id' => $employee2->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('companies.index', ['employee_id' => $employee1->id]));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($employee1->id, $data[0]['employee']['id']);
    }

    /** @test */
    public function authenticated_user_can_search_companies_by_name()
    {
        Company::factory()->create(['name' => 'Acme Corporation']);
        Company::factory()->create(['name' => 'Globex Corporation']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('companies.index', ['search' => 'Acme']));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Acme Corporation', $data[0]['name']);
    }

    /** @test */
    public function authenticated_user_can_create_company()
    {
        $employee = Employee::factory()->create();
        $payload = [
            'name' => 'Wayne Enterprises',
            'activity' => 'Technology',
            'address' => 'Gotham City',
            'employee_id' => $employee->id,
            'notes' => 'Important client.',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('companies.store'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Company created.',
            ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Wayne Enterprises',
            'employee_id' => $employee->id,
        ]);
    }

    /** @test */
    public function authenticated_user_can_show_company()
    {
        $company = Company::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('companies.show', $company->id));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'activity', 'address'],
            ]);
    }

    /** @test */
    public function authenticated_user_can_update_company()
    {
        $company = Company::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson(route('companies.update', $company->id), [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'New Name',
        ]);
    }

    /** @test */
    public function authenticated_user_can_delete_company()
    {
        $company = Company::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('companies.destroy', $company->id));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }
}
