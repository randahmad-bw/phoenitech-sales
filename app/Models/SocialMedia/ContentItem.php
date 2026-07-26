<?php

namespace App\Models\SocialMedia;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Content Item — individual content piece with 2-stage checkboxes (Designed & Published).
 */
class ContentItem extends Model
{
    protected $table = 'sm_content_items';

    protected $fillable = [
        'content_plan_id',
        'title',
        'content_type',
        'design_date',
        'publish_date',
        'assigned_to',
        'photo_session_id',
        'is_designed',
        'is_published',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'design_date' => 'date',
            'publish_date' => 'date',
            'is_designed' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function photoSession(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'photo_session_id');
    }
}
