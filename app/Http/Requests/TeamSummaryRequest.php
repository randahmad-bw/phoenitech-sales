<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Validation\Rule;

/**
 * The range and filters for the team's week/month view.
 *
 * Extends the history request rather than restating it: the range rules
 * (`period`, `from`/`to`, `month`) and the timezone-correct `range()` are the
 * same question being asked about a team instead of one person, and two copies
 * would drift.
 */
class TeamSummaryRequest extends AttendanceHistoryRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'department' => ['nullable', Rule::in(Employee::DEPARTMENTS)],
        ];
    }

    /**
     * @return array{department: string|null}
     */
    public function filters(): array
    {
        return ['department' => $this->input('department') ?: null];
    }
}
