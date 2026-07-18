<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment model representing a financial transaction against a contract.
 */
class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'contract_id',
        'amount',
        'exchange_rate',
        'payment_date',
        'method',
        'status',
        'notes',
    ];

    /**
     * Attribute type casting definitions.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'exchange_rate' => 'float',
            'payment_date' => 'date',
        ];
    }

    /**
     * The contract this payment belongs to.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
