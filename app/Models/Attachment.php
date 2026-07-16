<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Attachment model — polymorphic file storage for contracts and other entities.
 */
class Attachment extends Model
{
    use HasFactory;
    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
    ];

    /**
     * The parent model that owns this attachment.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Generate the public URL for this attachment.
     */
    public function getUrlAttribute(): ?string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
