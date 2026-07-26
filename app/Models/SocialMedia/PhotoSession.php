<?php

namespace App\Models\SocialMedia;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Photo Session — photography/video session for reels.
 */
class PhotoSession extends Model
{
    protected $table = 'sm_photo_sessions';

    protected $fillable = [
        'content_plan_id',
        'company_id',
        'session_date',
        'session_time',
        'photographer_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'photographer_id');
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'photo_session_id');
    }
}
