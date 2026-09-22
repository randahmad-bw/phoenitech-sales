<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Attachment model — polymorphic file storage for contracts, tasks and others.
 *
 * `attachable_type` holds the model class name, which is what Eloquent's own
 * `morphMany` writes and looks for. Clients speak in short aliases
 * (`contract`, `task`) and those are translated on the way in — see
 * `classFor()`. Storing the alias instead, as the upload endpoint once did,
 * produced rows that `$contract->attachments` could never find: the file was
 * uploaded, and the contract showed nothing.
 */
class Attachment extends Model
{
    use Auditable, HasFactory;

    /**
     * What a client may attach a file to, and the model behind each word.
     *
     * @var array<string, class-string<Model>>
     */
    public const ALIASES = [
        'contract' => Contract::class,
        'task' => Task::class,
    ];

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
     * The model class a client-facing alias refers to.
     *
     * @return class-string<Model>|null
     */
    public static function classFor(?string $alias): ?string
    {
        return self::ALIASES[$alias] ?? null;
    }

    /**
     * The folder name used on disk for a model class — `contract`, `task`.
     */
    public static function folderFor(string $attachableType): string
    {
        return Str::snake(class_basename($attachableType));
    }

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
        if (! $this->path) {
            return null;
        }

        $rawUrl = Storage::disk($this->disk)->url($this->path);

        // If rawUrl is an absolute external CDN / S3 URL, return as-is
        if (preg_match('/^https?:\/\/(?!localhost|127\.0\.0\.1)/i', $rawUrl)) {
            return $rawUrl;
        }

        $pathOnly = parse_url($rawUrl, PHP_URL_PATH) ?? ('/storage/'.ltrim($this->path, '/'));

        if (request()->hasHeader('host')) {
            return request()->schemeAndHttpHost().$pathOnly;
        }

        return $pathOnly;
    }
}
