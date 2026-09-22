<?php

namespace App\Http\Requests;

use App\Models\AttendanceSession;
use Illuminate\Validation\Rule;

/**
 * Validates management's decision on a recorded piece of extra work.
 *
 * The hours themselves are a fact the system recorded; whether they are owed
 * is a judgement, and this is where it is made. Rejecting requires a note —
 * telling someone their evening does not count deserves a reason on the record.
 */
class ReviewOvertimeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                AttendanceSession::OVERTIME_APPROVED,
                AttendanceSession::OVERTIME_REJECTED,
            ])],
            'review_note' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === AttendanceSession::OVERTIME_REJECTED),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'review_note.required' => 'A note is required when extra work is rejected.',
        ];
    }
}
