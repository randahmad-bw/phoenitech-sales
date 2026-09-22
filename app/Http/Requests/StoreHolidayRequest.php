<?php

namespace App\Http\Requests;

/**
 * Validates a company or public holiday.
 *
 * `is_recurring` marks a fixed-date annual holiday: the year stored on `date`
 * is then only the year it was first recorded, and matching ignores it.
 * Moveable feasts are entered per year instead.
 */
class StoreHolidayRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'is_recurring' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
