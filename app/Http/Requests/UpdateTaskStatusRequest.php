<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Validation\Rule;

/**
 * Validates a move between states.
 *
 * Cancelling must carry a note. Work that is called off without a reason is
 * the one case where the record is worse than useless later — nobody
 * remembers, and the task looks like it was simply dropped.
 */
class UpdateTaskStatusRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Task::STATUSES)],
            'completion_note' => [
                Rule::requiredIf(fn () => $this->input('status') === Task::STATUS_CANCELLED),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'completion_note.required' => 'Say why the task is being cancelled — it stays on the record.',
        ];
    }
}
