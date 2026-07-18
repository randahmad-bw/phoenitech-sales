<?php

namespace App\Application\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use App\Helpers\CurrencyHelper;

/**
 * Generates monthly and yearly reports with comparisons. All currency aggregates are converted to USD base.
 * Falls back to created_at year/month for contracts without a start_date.
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

        // Calculate best employee using USD converted totals
        $contractsForYear = Contract::where(function ($q) use ($year) {
            $q->whereYear('start_date', $year)
              ->orWhere(function ($sq) use ($year) {
                  $sq->whereNull('start_date')->whereYear('created_at', $year);
              });
        })
        ->whereNotNull('employee_id')
        ->with('employee:id,name')
        ->get();

        $employeeTotals = [];
        foreach ($contractsForYear as $contract) {
            $empId = $contract->employee_id;
            $empName = $contract->employee?->name ?? 'Unknown';
            if (!isset($employeeTotals[$empId])) {
                $employeeTotals[$empId] = ['name' => $empName, 'total' => 0.0];
            }
            $employeeTotals[$empId]['total'] += CurrencyHelper::toUsd((float)$contract->contract_value, $contract->currency);
        }

        usort($employeeTotals, fn($a, $b) => $b['total'] <=> $a['total']);
        $bestEmployee = !empty($employeeTotals) ? $employeeTotals[0] : null;

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
            'best_employee' => $bestEmployee ? ['name' => $bestEmployee['name'], 'total' => round($bestEmployee['total'], 2)] : null,
            'top_service' => $topService ? ['name_en' => $topService->service?->name_en, 'name_ar' => $topService->service?->name_ar, 'count' => $topService->count] : null,
        ];
    }

    /**
     * Get statistics for a specific month.
     */
    private function getMonthStats(int $year, int $month): array
    {
        $newCompanies = Company::whereYear('created_at', $year)->whereMonth('created_at', $month)->count();
        
        $contracts = Contract::where(function ($q) use ($year, $month) {
            $q->whereYear('start_date', $year)->whereMonth('start_date', $month)
              ->orWhere(function ($sq) use ($year, $month) {
                  $sq->whereNull('start_date')->whereYear('created_at', $year)->whereMonth('created_at', $month);
              });
        })->get();

        $totalValue = $contracts->sum(fn($c) => CurrencyHelper::toUsd((float)$c->contract_value, $c->currency));
        $newContracts = $contracts->count();
        
        $payments = Payment::where('status', 'paid')
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->with('contract')
            ->get();
        $collected = $payments->sum(fn($p) => CurrencyHelper::toUsd((float)$p->amount, $p->contract?->currency));

        return [
            'month' => $month, 'year' => $year,
            'new_companies' => $newCompanies, 'new_contracts' => $newContracts,
            'total_value' => round($totalValue, 2), 'collected' => round($collected, 2),
            'remaining' => round($totalValue - $collected, 2),
        ];
    }
}
