<?php

namespace App\Application\Services;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles file upload and deletion for attachments.
 */
class AttachmentService
{
    /**
     * Store an uploaded file and create an attachment record.
     */
    public function store(UploadedFile $file, string $attachableType, int $attachableId): Attachment
    {
        $storedName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "attachments/{$attachableType}/{$attachableId}";

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
        $attachment = Attachment::findOrFail($id);

        Storage::disk($attachment->disk)->delete($attachment->path);

        return $attachment->delete();
    }
}
