<?php

namespace App\Models\SocialMedia;

use App\Models\Company;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Content Plan — monthly plan for a social media contract.
 */
class ContentPlan extends Model
{
    protected $table = 'sm_content_plans';

    protected $fillable = [
        'contract_id',
        'company_id',
        'sm_package_id',
        'month',
        'year',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SmPackage::class, 'sm_package_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'content_plan_id');
    }

    public function photoSessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class, 'content_plan_id');
    }
}
