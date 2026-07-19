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
            'رقم العقد / Contract #',
            'اسم الشركة / Company Name',
            'تاريخ البداية / Start Date',
            'تاريخ الانتهاء / End Date',
            'المبلغ / Value',
            'العملة / Currency',
        ];
    }

    /**
     * @param \App\Models\Contract $contract
     */
    public function map($contract): array
    {
        return [
            $contract->contract_number,
            $contract->company?->name ?? '—',
            $contract->start_date?->format('Y-m-d') ?? '—',
            $contract->end_date?->format('Y-m-d') ?? '—',
            $contract->contract_value,
            $contract->currency,
        ];
    }
}
