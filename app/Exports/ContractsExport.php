<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContractsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $contracts;

    /**
     * @param \Illuminate\Support\Collection $contracts
     */
    public function __construct($contracts)
    {
        $this->contracts = $contracts;
    }

    public function collection()
    {
        return $this->contracts;
    }

    public function headings(): array
    {
        return [
            'Contract Number',
            'Company Name',
            'Employee Name',
            'Service (EN)',
            'Service (AR)',
            'Contract Value',
            'Currency',
            'Start Date',
            'End Date',
            'Status',
            'Progress (%)',
        ];
    }

    /**
     * @param \App\Models\Contract $contract
     */
    public function map($contract): array
    {
        return [
            $contract->contract_number,
            $contract->company?->name ?? 'N/A',
            $contract->employee?->name ?? 'N/A',
            $contract->service?->name_en ?? 'N/A',
            $contract->service?->name_ar ?? 'N/A',
            $contract->contract_value,
            $contract->currency,
            $contract->start_date?->format('Y-m-d') ?? '',
            $contract->end_date?->format('Y-m-d') ?? '',
            $contract->status,
            $contract->progress_percentage,
        ];
    }
}
