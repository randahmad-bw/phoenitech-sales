<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_export_contracts_as_pdf()
    {
        Contract::factory()->count(2)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('export.contracts', ['format' => 'pdf']));

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
    }

    /** @test */
    public function authenticated_user_can_export_contracts_as_excel()
    {
        Contract::factory()->count(2)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('export.contracts', ['format' => 'excel']));

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function authenticated_user_can_export_payments_as_csv()
    {
        Payment::factory()->count(2)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('export.payments', ['format' => 'csv']));

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** @test */
    public function authenticated_user_can_export_monthly_report_as_pdf()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('export.report', [
                'type' => 'monthly',
                'format' => 'pdf',
                'year' => 2026,
                'month' => 7,
            ]));

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
    }
}
