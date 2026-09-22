<?php

namespace App\Http\Requests;

use App\Models\EmployeeLeave;
use Illuminate\Validation\Rule;

/**
 * Validates management's decision on a leave request.
 *
 * A rejection must carry a note. Turning someone's leave down without a
 * recorded reason is the kind of thing that gets asked about weeks later, and
 * the record should be able to answer.
 */
class ReviewLeaveRequestRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                EmployeeLeave::STATUS_APPROVED,
                EmployeeLeave::STATUS_REJECTED,
            ])],
            'decision_note' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === EmployeeLeave::STATUS_REJECTED),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_note.required' => 'A note is required when a leave request is rejected.',
        ];
    }
}
