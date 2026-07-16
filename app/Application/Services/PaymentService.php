<?php

namespace App\Application\Services;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles payment CRUD operations scoped to a contract.
 */
class PaymentService
{
    /**
     * List all payments for a given contract.
     */
    public function listByContract(int $contractId): Collection
    {
        return Payment::where('contract_id', $contractId)
            ->orderByDesc('payment_date')
            ->get();
    }

    /**
     * Create a new payment for a contract.
     */
    public function create(int $contractId, array $data): Payment
    {
        $data['contract_id'] = $contractId;
        return Payment::create($data);
    }

    /**
     * Update an existing payment record.
     */
    public function update(int $id, array $data): Payment
    {
        $payment = Payment::findOrFail($id);
        $payment->update($data);
        return $payment->fresh();
    }

    /**
     * Delete a payment record.
     */
    public function delete(int $id): bool
    {
        return Payment::findOrFail($id)->delete();
    }
}
