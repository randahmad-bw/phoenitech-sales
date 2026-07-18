<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract model — core business entity linking company, employee, and service.
 * Provides computed accessors for financial totals.
 */
class Contract extends Model
{
    use HasFactory;
    protected $fillable = [
        'parent_contract_id',
        'contract_number',
        'company_id',
        'employee_id',
        'service_id',
        'contract_value',
        'currency',
        'exchange_rate',
        'start_date',
        'end_date',
        'status',
        'progress_percentage',
        'notes',
    ];

    /**
     * Attribute type casting definitions.
     */
    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'exchange_rate' => 'float',
            'start_date' => 'date',
            'end_date' => 'date',
            'progress_percentage' => 'integer',
        ];
    }

    /**
     * The company this contract belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The employee assigned to this contract.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The parent contract if this is a renewal.
     */
    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'parent_contract_id');
    }

    /**
     * The renewal contracts that were generated from this contract.
     */
    public function renewals(): HasMany
    {
        return $this->hasMany(Contract::class, 'parent_contract_id');
    }

    /**
     * Change history entries for this contract.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ContractHistory::class)->orderByDesc('created_at');
    }

    /**
     * Get the root parent contract ID (follows the chain up to the origin).
     * If this IS the root, returns its own id.
     */
    public function getRootParentId(): int
    {
        return $this->parent_contract_id ?? $this->id;
    }

    /**
     * The service type of this contract.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Payment records for this contract.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * File attachments for this contract (polymorphic).
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Calculate total paid amount from confirmed payments.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->where('status', 'paid')->sum('amount');
    }

    /**
     * Calculate remaining amount to be collected.
     */
    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->contract_value - $this->total_paid;
    }

    /**
     * Calculate collection percentage (0-100).
     */
    public function getCollectionPercentageAttribute(): float
    {
        if ((float) $this->contract_value <= 0) {
            return 0;
        }

        return round(($this->total_paid / (float) $this->contract_value) * 100, 2);
    }
}
