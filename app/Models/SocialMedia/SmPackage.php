<?php

namespace App\Models\SocialMedia;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SM Package — defines the monthly content quota for a social media contract.
 */
class SmPackage extends Model
{
    protected $fillable = [
        'contract_id',
        'package_name',
        'monthly_posts',
        'monthly_reels',
        'monthly_stories',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_posts' => 'integer',
            'monthly_reels' => 'integer',
            'monthly_stories' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function contentPlans(): HasMany
    {
        return $this->hasMany(ContentPlan::class, 'sm_package_id');
    }
}
