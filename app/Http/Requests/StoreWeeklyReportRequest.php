<?php

namespace App\Http\Requests;

/**
 * Validates weekly report creation and update data.
 */
class StoreWeeklyReportRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'week_start_date' => ['required', 'date'],
            
            // KPIs
            'kpis' => ['required', 'array'],
            'kpis.total_contacted' => ['required', 'integer', 'min:0'],
            'kpis.doctors' => ['required', 'integer', 'min:0'],
            'kpis.medical_centers' => ['required', 'integer', 'min:0'],
            'kpis.schools' => ['required', 'integer', 'min:0'],
            'kpis.restaurants_cafeterias' => ['required', 'integer', 'min:0'],
            'kpis.pending_decision' => ['required', 'integer', 'min:0'],
            'kpis.price_offers' => ['required', 'integer', 'min:0'],
            
            // Pipeline & Probability
            'pipeline' => ['required', 'array'],
            'pipeline.signed' => ['nullable', 'array'],
            'pipeline.signed.*.name' => ['required', 'string', 'max:255'],
            'pipeline.signed.*.completion_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'pipeline.pending' => ['nullable', 'array'],
            'pipeline.pending.*' => ['required', 'string', 'max:255'],
            
            // Next Plan
            'next_plan' => ['required', 'array'],
            'next_plan.follow_ups' => ['nullable', 'array'],
            'next_plan.follow_ups.*' => ['required', 'string', 'max:500'],
            'next_plan.improvement_strategy' => ['required', 'string', 'max:2000'],
            
            // Notes
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
