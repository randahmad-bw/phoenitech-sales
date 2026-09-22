<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Validation\Rule;

/**
 * Validates the task listing's query string.
 *
 * Filters are validated for the same reason a body is: an unchecked `status`
 * reaches the query builder, matches nothing, and the screen shows an empty
 * list that looks like an answer.
 */
class TaskFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // `open` is not a stored status but the one people ask for most:
            // everything still on someone's plate.
            'status' => ['nullable', Rule::in([...Task::STATUSES, 'open'])],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'assigned_to' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'overdue' => ['nullable', 'boolean'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * The filters as the service expects them — absent keys dropped, so
     * `when()` is never handed an empty string to filter on.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = array_filter(
            $this->safe()->only([
                'status', 'priority', 'assigned_to', 'company_id',
                'due_from', 'due_to', 'search', 'per_page',
            ]),
            fn ($value) => $value !== null && $value !== ''
        );

        if ($this->boolean('overdue')) {
            $filters['overdue'] = true;
        }

        return $filters;
    }
}
