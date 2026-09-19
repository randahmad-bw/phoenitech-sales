<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Attachment model — polymorphic file storage for contracts and other entities.
 */
class Attachment extends Model
{
    use Auditable, HasFactory;
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
     * Generate the public URL for this attachment, resolving host dynamically from request.
     */
    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        $rawUrl = Storage::disk($this->disk)->url($this->path);

        // If rawUrl is an absolute external CDN / S3 URL, return as-is
        if (preg_match('/^https?:\/\/(?!localhost|127\.0\.0\.1)/i', $rawUrl)) {
            return $rawUrl;
        }

        $pathOnly = parse_url($rawUrl, PHP_URL_PATH) ?? ('/storage/' . ltrim($this->path, '/'));

        if (request()->hasHeader('host')) {
            return request()->schemeAndHttpHost() . $pathOnly;
        }

        return $pathOnly;
    }
}
