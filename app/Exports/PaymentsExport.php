<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $payments;

    /**
     * @param \Illuminate\Support\Collection $payments
     */
    public function __construct($payments)
    {
        $this->payments = $payments;
    }

    public function collection()
    {
        return $this->payments;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Contract Number',
            'Company Name',
            'Amount',
            'Payment Date',
            'Method',
            'Status',
            'Notes',
        ];
    }

    /**
     * @param \App\Models\Payment $payment
     */
    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->contract?->contract_number ?? 'N/A',
            $payment->contract?->company?->name ?? 'N/A',
            $payment->amount,
            $payment->payment_date?->format('Y-m-d') ?? '',
            $payment->method,
            $payment->status,
            $payment->notes,
        ];
    }
}
