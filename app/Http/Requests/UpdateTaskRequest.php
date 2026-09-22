<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Validation\Rule;

/**
 * Validates an edit to a task's particulars.
 *
 * Status is not among them — moving a task writes timestamps, so it goes
 * through its own endpoint (`PATCH tasks/{task}/status`) which every mover,
 * manager or assignee, uses.
 */
class UpdateTaskRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['sometimes', 'required', 'integer', 'exists:employees,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'priority' => ['sometimes', 'required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
