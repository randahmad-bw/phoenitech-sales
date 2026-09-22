<?php

namespace App\Http\Requests;

/**
 * Validates export query data.
 */
class ExportRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:pdf,excel,csv'],
            'type' => ['nullable', 'string', 'in:monthly,yearly'],
            'search' => ['nullable', 'string'],
            'company_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer'],
            'contract_id' => ['nullable', 'integer'],
            'payment_date_from' => ['nullable', 'date'],
            'payment_date_to' => ['nullable', 'date'],
            'method' => ['nullable', 'string'],
        ];
    }
}
