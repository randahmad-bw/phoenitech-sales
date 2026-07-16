<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks field-level changes on contracts (create, update, renew).
 */
class ContractHistory extends Model
{
    protected $fillable = [
        'contract_id',
        'field_name',
        'old_value',
        'new_value',
        'action',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
