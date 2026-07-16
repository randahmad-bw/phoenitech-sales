<?php

namespace App\Application\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;

/**
 * Generates monthly and yearly reports with comparisons.
 */
class ReportService
{
    /**
     * Generate a monthly report with comparison to previous month.
     */
    public function monthlyReport(int $year, int $month): array
    {
        $current = $this->getMonthStats($year, $month);
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear = $month === 1 ? $year - 1 : $year;
        $previous = $this->getMonthStats($prevYear, $prevMonth);

        return [
            'current' => $current,
            'previous' => $previous,
            'comparison' => [
                'new_companies_diff' => $current['new_companies'] - $previous['new_companies'],
                'new_contracts_diff' => $current['new_contracts'] - $previous['new_contracts'],
                'total_value_diff' => round($current['total_value'] - $previous['total_value'], 2),
                'collected_diff' => round($current['collected'] - $previous['collected'], 2),
            ],
        ];
    }

    /**
     * Generate a yearly report with monthly breakdown and highlights.
     */
    public function yearlyReport(int $year): array
    {
        $monthlyBreakdown = [];
        $bestMonth = ['month' => 0, 'value' => 0];

        for ($m = 1; $m <= 12; $m++) {
            $stats = $this->getMonthStats($year, $m);
            $monthlyBreakdown[$m] = $stats;
            if ($stats['total_value'] > $bestMonth['value']) {
                $bestMonth = ['month' => $m, 'value' => $stats['total_value']];
            }
        }

        $bestEmployee = Contract::selectRaw('employee_id, SUM(contract_value) as total')
            ->with('employee:id,name')
            ->whereYear('start_date', $year)
            ->whereNotNull('employee_id')
            ->groupBy('employee_id')
            ->orderByDesc('total')
            ->first();

        $topService = Contract::selectRaw('service_id, COUNT(*) as count')
            ->with('service:id,name_en,name_ar')
            ->whereYear('start_date', $year)
            ->whereNotNull('service_id')
            ->groupBy('service_id')
            ->orderByDesc('count')
            ->first();

        return [
            'year' => $year,
            'monthly_breakdown' => $monthlyBreakdown,
            'best_month' => $bestMonth,
            'best_employee' => $bestEmployee ? ['name' => $bestEmployee->employee?->name, 'total' => round($bestEmployee->total, 2)] : null,
            'top_service' => $topService ? ['name_en' => $topService->service?->name_en, 'name_ar' => $topService->service?->name_ar, 'count' => $topService->count] : null,
        ];
    }

    /**
     * Get statistics for a specific month.
     */
    private function getMonthStats(int $year, int $month): array
    {
        $newCompanies = Company::whereYear('created_at', $year)->whereMonth('created_at', $month)->count();
        $contracts = Contract::whereYear('start_date', $year)->whereMonth('start_date', $month);
        $totalValue = (clone $contracts)->sum('contract_value');
        $newContracts = (clone $contracts)->count();
        $collected = Payment::where('status', 'paid')->whereYear('payment_date', $year)->whereMonth('payment_date', $month)->sum('amount');

        return [
            'month' => $month, 'year' => $year,
            'new_companies' => $newCompanies, 'new_contracts' => $newContracts,
            'total_value' => round($totalValue, 2), 'collected' => round($collected, 2),
            'remaining' => round($totalValue - $collected, 2),
        ];
    }
}
