<?php

namespace App\Application\Services;

use App\Exports\ContractsExport;
use App\Exports\PaymentsExport;
use App\Models\Contract;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles exporting contracts, payments, and monthly/yearly reports
 * into PDF, Excel, or CSV formats.
 */
class ExportService
{
    public function __construct(private ReportService $reportService) {}

    /**
     * Export contracts based on filters and format.
     */
    public function exportContracts(array $filters, string $format): Response
    {
        $query = Contract::with(['company', 'employee', 'service']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                  ->orWhereHas('company', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('start_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('start_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->whereMonth('start_date', $filters['month']);
        }

        $contracts = $query->latest()->get();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.contracts', compact('contracts'));
            return $pdf->download('contracts_' . now()->format('YmdHis') . '.pdf');
        }

        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $excelFormat = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        return Excel::download(
            new ContractsExport($contracts),
            'contracts_' . now()->format('YmdHis') . '.' . $extension,
            $excelFormat
        );
    }

    /**
     * Export payments based on filters and format.
     */
    public function exportPayments(array $filters, string $format): Response
    {
        $query = Payment::with(['contract.company']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('contract.company', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('contract', fn ($ctq) => $ctq->where('contract_number', 'like', "%{$search}%"));
            });
        }

        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['payment_date_from']);
        }

        if (!empty($filters['payment_date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['payment_date_to']);
        }

        if (!empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        $payments = $query->orderByDesc('payment_date')->get();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.payments', compact('payments'));
            return $pdf->download('payments_' . now()->format('YmdHis') . '.pdf');
        }

        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $excelFormat = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        return Excel::download(
            new PaymentsExport($payments),
            'payments_' . now()->format('YmdHis') . '.' . $extension,
            $excelFormat
        );
    }

    /**
     * Export monthly or yearly report based on parameters and format.
     */
    public function exportReport(string $type, array $params, string $format): Response
    {
        $year = (int) ($params['year'] ?? now()->year);

        if ($type === 'monthly') {
            $month = (int) ($params['month'] ?? now()->month);
            $report = $this->reportService->monthlyReport($year, $month);

            if ($format === 'pdf') {
                $pdf = Pdf::loadView('exports.report_monthly', compact('report', 'year', 'month'));
                return $pdf->download("monthly_report_{$year}_{$month}.pdf");
            }

            // Simple Excel export for monthly report as collection mapping
            $data = collect([
                ['Metric', 'Previous Month', 'Current Month', 'Difference'],
                ['New Companies', $report['previous']['new_companies'], $report['current']['new_companies'], $report['comparison']['new_companies_diff']],
                ['New Contracts', $report['previous']['new_contracts'], $report['current']['new_contracts'], $report['comparison']['new_contracts_diff']],
                ['Total Value', $report['previous']['total_value'], $report['current']['total_value'], $report['comparison']['total_value_diff']],
                ['Collected', $report['previous']['collected'], $report['current']['collected'], $report['comparison']['collected_diff']],
            ]);

            return Excel::download(
                new class($data) implements FromCollection {
                    public function __construct(private $data) {}
                    public function collection() { return $this->data; }
                },
                "monthly_report_{$year}_{$month}.xlsx"
            );
        } else {
            $report = $this->reportService->yearlyReport($year);

            if ($format === 'pdf') {
                $pdf = Pdf::loadView('exports.report_yearly', compact('report', 'year'));
                return $pdf->download("yearly_report_{$year}.pdf");
            }

            // Simple Excel export for yearly report
            $rows = [
                ['Yearly Highlights', ''],
                ['Best Month', 'Month ' . $report['best_month']['month'] . ' (' . $report['best_month']['value'] . ' USD)'],
                ['Top Employee', ($report['best_employee']['name'] ?? 'N/A') . ' (' . ($report['best_employee']['total'] ?? 0) . ' USD)'],
                ['Top Service', ($report['top_service']['name_en'] ?? 'N/A') . ' (' . ($report['top_service']['count'] ?? 0) . ' contracts)'],
                [],
                ['Month', 'New Companies', 'New Contracts', 'Total Value (USD)', 'Collected (USD)', 'Remaining (USD)'],
            ];

            foreach ($report['monthly_breakdown'] as $m => $stats) {
                $rows[] = [
                    'Month ' . $m,
                    $stats['new_companies'],
                    $stats['new_contracts'],
                    $stats['total_value'],
                    $stats['collected'],
                    $stats['remaining'],
                ];
            }

            return Excel::download(
                new class(collect($rows)) implements FromCollection {
                    public function __construct(private $data) {}
                    public function collection() { return $this->data; }
                },
                "yearly_report_{$year}.xlsx"
            );
        }
    }
}
