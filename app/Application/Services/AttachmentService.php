<?php

namespace App\Application\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles file upload and deletion for attachments.
 *
 * `$attachableType` is a **model class name**, not a client alias — the
 * translation happens in the form request, so the value written here is the
 * one Eloquent's morph relations look for.
 */
class AttachmentService
{
    /**
     * Store an uploaded file and create an attachment record.
     *
     * @param  class-string<Model>  $attachableType
     */
    public function store(UploadedFile $file, string $attachableType, int $attachableId): Attachment
    {
        $storedName = Str::uuid().'.'.$file->getClientOriginalExtension();
        // The folder keeps the short name — `attachments/contract/12` reads
        // better on disk than the namespace does, and nothing resolves models
        // from the path.
        $path = 'attachments/'.Attachment::folderFor($attachableType)."/{$attachableId}";

        $file->storeAs($path, $storedName, 'public');

        return Attachment::create([
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => 'public',
            'path' => "{$path}/{$storedName}",
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    /**
     * Delete an attachment and its physical file from storage.
     */
    public function delete(int $id): bool
    {
        return $this->deleteAttachment(Attachment::findOrFail($id));
    }

    /**
     * Delete an attachment already fetched — used where the caller has
     * established the file belongs to the record it is being removed from.
     */
    public function deleteAttachment(Attachment $attachment): bool
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        return (bool) $attachment->delete();
    }
}
