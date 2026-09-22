<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates file upload for attachments.
 *
 * The client sends an alias (`contract`); the column stores the model class.
 * Translating here rather than in the controller keeps the mapping in one
 * place and out of the request body's reach.
 */
class UploadAttachmentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // This endpoint is the contracts one. Tasks attach through
            // `POST tasks/{task}/attachments`, where the task's own permission
            // and ownership checks apply.
            'attachable_type' => ['required', Rule::in(['contract'])],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ];
    }

    /**
     * The model class the alias in the request refers to.
     *
     * @return class-string<Model>
     */
    public function attachableClass(): string
    {
        return Attachment::classFor($this->string('attachable_type')->toString());
    }
}
