<?php

namespace App\Http\Requests;

/**
 * Validates file upload for attachments.
 */
class UploadAttachmentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'in:contract'],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ];
    }
}
