<?php

namespace App\Http\Requests;

/**
 * Validates a change to one checklist line: its wording, or its tick.
 *
 * Both are `sometimes`, because the two reach this endpoint from different
 * places — the box is pressed on its own, the text is corrected on its own —
 * and a rule that demanded both would make each one send the other back
 * unchanged.
 *
 * `completed_at` is not accepted. When a line was finished is the server's
 * answer, written at the moment the tick arrives.
 */
class UpdateTaskItemRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'done' => ['sometimes', 'boolean'],
        ];
    }
}
