<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->contract = Contract::factory()->create(['contract_value' => 10000.00]);
    }

    /** @test */
    public function authenticated_user_can_list_payments_for_contract()
    {
        Payment::factory()->count(2)->create(['contract_id' => $this->contract->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('payments.index', $this->contract->id));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    /** @test */
    public function authenticated_user_can_create_payment_and_recalculate_totals()
    {
        $payload = [
            'amount' => 4000.00,
            'payment_date' => '2026-01-15',
            'method' => 'bank_transfer',
            'status' => 'paid',
            'notes' => 'First installment.',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('payments.store', $this->contract->id), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('payments', [
            'contract_id' => $this->contract->id,
            'amount' => 4000.00,
            'status' => 'paid',
        ]);

        // Refresh contract and verify computed totals
        $this->contract->refresh();
        $this->assertEquals(4000.00, $this->contract->total_paid);
        $this->assertEquals(6000.00, $this->contract->remaining_amount);
        $this->assertEquals(40.0, $this->contract->collection_percentage);
    }

    /** @test */
    public function authenticated_user_can_update_payment_status_and_recalculate_totals()
    {
        $payment = Payment::factory()->create([
            'contract_id' => $this->contract->id,
            'amount' => 5000.00,
            'status' => 'pending',
        ]);

        // Prior to update, total paid should be 0 because status is pending
        $this->assertEquals(0, $this->contract->total_paid);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson(route('payments.update', [$this->contract->id, $payment->id]), [
                'status' => 'paid',
            ]);

        $response->assertStatus(200);

        // After update, total paid should be 5000
        $this->contract->refresh();
        $this->assertEquals(5000.00, $this->contract->total_paid);
        $this->assertEquals(5000.00, $this->contract->remaining_amount);
    }

    /** @test */
    public function authenticated_user_can_delete_payment()
    {
        $payment = Payment::factory()->create([
            'contract_id' => $this->contract->id,
            'amount' => 3000.00,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('payments.destroy', [$this->contract->id, $payment->id]));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);

        $this->contract->refresh();
        $this->assertEquals(0, $this->contract->total_paid);
    }
}
