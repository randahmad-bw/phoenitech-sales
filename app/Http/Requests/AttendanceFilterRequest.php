<?php

namespace App\Http\Requests;

use App\Application\Support\ScheduleResolver;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Validates the management dashboard's filters.
 *
 * The date defaults to today in the company timezone, not UTC — otherwise the
 * dashboard would roll over to tomorrow at 21:00 local time.
 */
class AttendanceFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department' => ['nullable', 'string', 'max:50'],
            // `pending` is filterable here even though it is never stored: the
            // grid derives it, so management can ask "who has not arrived yet?".
            'status' => ['nullable', Rule::in([...Attendance::STATUSES, Attendance::STATUS_PENDING])],
        ];
    }

    /**
     * The day being looked at.
     */
    public function resolveDate(ScheduleResolver $resolver): CarbonImmutable
    {
        if (! $this->filled('date')) {
            return $resolver->today();
        }

        return CarbonImmutable::parse($this->input('date'), config('attendance.timezone'))->startOfDay();
    }

    /**
     * @return array{employee_id: int|null, department: string|null, status: string|null}
     */
    public function filters(): array
    {
        return [
            'employee_id' => $this->input('employee_id'),
            'department' => $this->input('department'),
            'status' => $this->input('status'),
        ];
    }
}
