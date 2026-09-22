<?php

namespace App\Http\Requests;

/**
 * Validates a file attached to a task.
 *
 * The same size and type limits as the contract uploader, plus the image and
 * archive formats the production team actually hands back: a design comes as a
 * PNG or a zip far more often than as a spreadsheet.
 */
class StoreTaskAttachmentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,gif,webp,svg,zip,rar',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The file must not be larger than 10 MB.',
        ];
    }
}
