<?php

namespace App\Http\Requests;

/**
 * Validates a new line on a task's checklist.
 *
 * A title and nothing else. There is no `done` here on purpose: a line is
 * written before it is finished, and one created as already-ticked records a
 * completion time for a moment nobody was working.
 */
class StoreTaskItemRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Write what the line is before adding it.',
        ];
    }
}
