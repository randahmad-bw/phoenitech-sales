<?php

namespace App\Models\SocialMedia;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SM Package — defines monthly content quotas & pricing for social media.
 * Can be a global template (contract_id = null) or contract-specific.
 */
class SmPackage extends Model
{
    protected $fillable = [
        'contract_id',
        'package_name',
        'price',
        'monthly_posts',
        'monthly_reels',
        'monthly_stories',
        'boost_reel_cost',
        'boost_post_cost',
        'boost_story_cost',
        'is_custom',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_posts'    => 'integer',
            'monthly_reels'    => 'integer',
            'monthly_stories'  => 'integer',
            'price'            => 'decimal:2',
            'boost_reel_cost'  => 'decimal:2',
            'boost_post_cost'  => 'decimal:2',
            'boost_story_cost' => 'decimal:2',
            'is_custom'        => 'boolean',
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

