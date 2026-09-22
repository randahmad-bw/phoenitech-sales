<?php

namespace App\Http\Requests;

/**
 * Validates one line on a task's follow-up thread.
 */
class StoreTaskCommentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
