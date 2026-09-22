<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Validation\Rule;

/**
 * Validates a new task.
 *
 * Note what is missing: **no `status`, and no timestamps.** A task starts at
 * `todo` and its start and finish times are written when it actually moves, so
 * there is nowhere for a client to put a state the work has not reached.
 */
class StoreTaskRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Required, not nullable: an unowned task is nobody's problem.
            'assigned_to' => ['required', 'integer', 'exists:employees,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            // The lines, written with the task. Optional, because plenty of
            // work is one motion — but offered here because a manager who has
            // to save the task and reopen it to list what is in it will not
            // list what is in it.
            'items' => ['nullable', 'array', 'max:50'],
            // Blank entries pass and are dropped by the service: the form
            // keeps an empty row ready for the next line, and refusing it would
            // make every task fail to save on the first try.
            'items.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_to.required' => 'Choose who is responsible for this task.',
        ];
    }
}
