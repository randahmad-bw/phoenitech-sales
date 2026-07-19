<?php

namespace App\Application\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use App\Helpers\CurrencyHelper;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates dashboard statistics and chart data. All aggregates are converted to USD base.
 * Falls back to created_at year/month for contracts without a start_date.
 */
class DashboardService
{
    /**
     * Retrieve all KPI stats and chart data for the dashboard.
     */
    public function getData(int $year = null): array
    {
        $year = $year ?? now()->year;

        return [
            'stats' => $this->getOverviewStats($year),
            'charts' => [
                'monthly_sales' => $this->getMonthlySalesChart($year),
                'monthly_collections' => $this->getMonthlyCollectionsChart($year),
                'contracts_by_status' => $this->getContractsByStatusChart(),
                'top_employees' => $this->getTopEmployeesChart($year),
                'top_services' => $this->getTopServicesChart($year),
                'year_comparison' => $this->getYearComparisonChart($year),
                'employee_monthly_contracts' => $this->getEmployeeMonthlyContracts($year),
            ],
        ];
    }

    /**
     * Convert contract value to USD using the contract's actual stored exchange rate.
     */
    private function contractToUsd(Contract $contract, float $amount): float
    {
        if ($contract->currency === 'USD') {
            return $amount;
        }
        $rate = $contract->exchange_rate;
        if ($rate && (float)$rate > 0 && (float)$rate !== 1.0) {
            return $amount / (float)$rate;
        }
        return CurrencyHelper::toUsd($amount, $contract->currency);
    }

    /**
     * Convert payment amount to USD using payment or contract stored exchange rate.
     */
    private function paymentToUsd(Payment $payment, float $amount, ?Contract $contract = null): float
    {
        $contract = $contract ?? $payment->contract;
        $currency = $contract?->currency ?? 'USD';
        
        if ($currency === 'USD') {
            return $amount;
        }
        
        $payRate = $payment->exchange_rate;
        if ($payRate && (float)$payRate > 0 && (float)$payRate !== 1.0) {
            return $amount / (float)$payRate;
        }
        
        if ($contract) {
            $conRate = $contract->exchange_rate;
            if ($conRate && (float)$conRate > 0 && (float)$conRate !== 1.0) {
                return $amount / (float)$conRate;
            }
        }
        
        return CurrencyHelper::toUsd($amount, $currency);
    }

    /**
     * Calculate overview KPI stats.
     */
    private function getOverviewStats(int $year): array
    {
        $contracts = Contract::where(function ($q) use ($year) {
            $q->whereYear('start_date', $year)
              ->orWhere(function ($sq) use ($year) {
                  $sq->whereNull('start_date')->whereYear('created_at', $year);
              });
        })->with('payments')->get();

        $totalValue = $contracts->sum(fn($c) => $this->contractToUsd($c, (float)$c->contract_value));
        
        // Calculate total paid strictly for the contracts belonging to this year
        $totalPaid = 0;
        foreach ($contracts as $c) {
            foreach ($c->payments as $p) {
                if ($p->status === 'paid') {
                    $totalPaid += $this->paymentToUsd($p, (float)$p->amount, $c);
                }
            }
        }
        
        $thisMonth = now()->month;

        $contractsThisMonth = Contract::where(function ($q) use ($thisMonth, $year) {
            $q->whereMonth('start_date', $thisMonth)->whereYear('start_date', $year)
              ->orWhere(function ($sq) use ($thisMonth, $year) {
                  $sq->whereNull('start_date')->whereMonth('created_at', $thisMonth)->whereYear('created_at', $year);
              });
        })->get();
        
        $salesThisMonth = $contractsThisMonth->sum(fn($c) => $this->contractToUsd($c, (float)$c->contract_value));

        $paymentsThisMonth = Payment::where('status', 'paid')
            ->whereMonth('payment_date', $thisMonth)
            ->whereYear('payment_date', $year)
            ->with('contract')
            ->get();
        $collectedThisMonth = $paymentsThisMonth->sum(fn($p) => $this->paymentToUsd($p, (float)$p->amount));

        $parentContracts = $contracts->whereNull('parent_contract_id');
        $newContractsThisMonth = $contractsThisMonth->whereNull('parent_contract_id');
        $renewedContractsThisMonth = $contractsThisMonth->whereNotNull('parent_contract_id');

        return [
            'total_companies' => Company::whereYear('created_at', '<=', $year)->count(),
            'total_contacts' => DB::table('contacts')->whereYear('created_at', '<=', $year)->count(),
            'total_contracts' => $parentContracts->count(),
            'active_contracts' => $contracts->where('status', 'active')->count(),
            'completed_contracts' => $parentContracts->where('status', 'completed')->count(),
            'cancelled_contracts' => $parentContracts->where('status', 'cancelled')->count(),
            'expired_contracts' => Contract::whereYear('start_date', $year)->whereNull('parent_contract_id')->where('end_date', '<', now())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'total_contract_value' => round($totalValue, 2),
            'total_paid' => round($totalPaid, 2),
            'total_remaining' => round($totalValue - $totalPaid, 2),
            'collection_percentage' => $totalValue > 0 ? round(($totalPaid / $totalValue) * 100, 2) : 0,
            'avg_contract_value' => $parentContracts->count() > 0 ? round($parentContracts->sum(fn($c) => $this->contractToUsd($c, (float)$c->contract_value)) / $parentContracts->count(), 2) : 0,
            'largest_contract' => round($contracts->map(fn($c) => $this->contractToUsd($c, (float)$c->contract_value))->max() ?? 0, 2),
            'new_contracts_this_month' => $newContractsThisMonth->count(),
            'renewed_contracts_this_month' => $renewedContractsThisMonth->count(),
            'new_companies_this_month' => Company::whereMonth('created_at', $thisMonth)->whereYear('created_at', $year)->count(),
            'sales_this_month'         => round($salesThisMonth, 2),
            'total_sales'              => round($totalValue, 2),
            'collected_this_month'     => round($collectedThisMonth, 2),
        ];
    }

    /**
     * Generate monthly sales chart data for a given year.
     */
    private function getMonthlySalesChart(int $year): array
    {
        $contracts = Contract::where(function ($q) use ($year) {
            $q->whereYear('start_date', $year)
              ->orWhere(function ($sq) use ($year) {
                  $sq->whereNull('start_date')->whereYear('created_at', $year);
              });
        })->get();
        
        $data = [];
        foreach ($contracts as $contract) {
            $month = $contract->start_date?->month ?? $contract->created_at?->month;
            if ($month) {
                if (!isset($data[$month])) {
                    $data[$month] = 0.0;
                }
                $data[$month] += $this->contractToUsd($contract, (float)$contract->contract_value);
            }
        }

        return $this->fillMonthlyData($data);
    }

    /**
     * Generate monthly collections chart data for a given year.
     */
    private function getMonthlyCollectionsChart(int $year): array
    {
        $payments = Payment::where('status', 'paid')
            ->whereYear('payment_date', $year)
            ->with('contract')
            ->get();

        $data = [];
        foreach ($payments as $payment) {
            $month = $payment->payment_date?->month;
            if ($month) {
                if (!isset($data[$month])) {
                    $data[$month] = 0.0;
                }
                $data[$month] += $this->paymentToUsd($payment, (float)$payment->amount);
            }
        }

        return $this->fillMonthlyData($data);
    }

    /**
     * Generate contract count by status for pie chart.
     */
    private function getContractsByStatusChart(): array
    {
        return Contract::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Generate top 5 employees by total contract value.
     */
    private function getTopEmployeesChart(int $year): array
    {
        $contracts = Contract::where(function ($q) use ($year) {
            $q->whereYear('start_date', $year)
              ->orWhere(function ($sq) use ($year) {
                  $sq->whereNull('start_date')->whereYear('created_at', $year);
              });
        })
        ->whereNotNull('employee_id')
        ->with('employee:id,name')
        ->get();

        $grouped = [];
        foreach ($contracts as $contract) {
            $empId = $contract->employee_id;
            $empName = $contract->employee?->name ?? 'Unknown';
            if (!isset($grouped[$empId])) {
                $grouped[$empId] = ['name' => $empName, 'total' => 0.0];
            }
            $grouped[$empId]['total'] += $this->contractToUsd($contract, (float)$contract->contract_value);
        }

        usort($grouped, fn($a, $b) => $b['total'] <=> $a['total']);

        return array_slice(array_map(fn($item) => [
            'name' => $item['name'],
            'total' => round($item['total'], 2),
        ], $grouped), 0, 5);
    }

    /**
     * Generate top 5 services by total contract value.
     */
    private function getTopServicesChart(int $year): array
    {
        $contracts = Contract::where(function ($q) use ($year) {
            $q->whereYear('start_date', $year)
              ->orWhere(function ($sq) use ($year) {
                  $sq->whereNull('start_date')->whereYear('created_at', $year);
              });
        })
        ->whereNotNull('service_id')
        ->with('service:id,name_ar,name_en')
        ->get();

        $grouped = [];
        foreach ($contracts as $contract) {
            $srvId = $contract->service_id;
            $srvAr = $contract->service?->name_ar ?? 'غير معروف';
            $srvEn = $contract->service?->name_en ?? 'Unknown';
            if (!isset($grouped[$srvId])) {
                $grouped[$srvId] = ['name_ar' => $srvAr, 'name_en' => $srvEn, 'count' => 0, 'total' => 0.0];
            }
            $grouped[$srvId]['count']++;
            $grouped[$srvId]['total'] += $this->contractToUsd($contract, (float)$contract->contract_value);
        }

        usort($grouped, fn($a, $b) => $b['total'] <=> $a['total']);

        return array_slice(array_map(fn($item) => [
            'name_ar' => $item['name_ar'],
            'name_en' => $item['name_en'],
            'count' => $item['count'],
            'total' => round($item['total'], 2),
        ], $grouped), 0, 5);
    }

    /**
     * Generate year-over-year comparison chart data.
     */
    private function getYearComparisonChart(int $year): array
    {
        $currentYear = $this->getMonthlySalesChart($year);
        $previousYear = $this->getMonthlySalesChart($year - 1);

        return [
            'current_year' => $currentYear,
            'previous_year' => $previousYear,
        ];
    }

    /**
     * Generate employee performance stats: contracts count this month vs previous month.
     */
    private function getEmployeeMonthlyContracts(int $year): array
    {
        $now         = now();
        $thisMonth   = $now->month;
        $thisYear    = $year;
        $prevMonth   = $now->copy()->subMonth()->month;
        $prevYear    = $thisMonth == 1 ? $year - 1 : $year;

        $employees = DB::table('employees')
            ->select('id', 'name')
            ->get();

        $data = [];
        foreach ($employees as $employee) {
            // ── This month ────────────────────────────
            $contractsThisMonth = Contract::where('employee_id', $employee->id)
                ->where(function ($q) use ($thisMonth, $thisYear) {
                    $q->whereMonth('start_date', $thisMonth)->whereYear('start_date', $thisYear)
                      ->orWhere(function ($sq) use ($thisMonth, $thisYear) {
                          $sq->whereNull('start_date')->whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear);
                      });
                })->get();
            $salesThisMonth = $contractsThisMonth->sum(fn($c) => $this->contractToUsd($c, (float)$c->contract_value));

            $paymentsThisMonth = Payment::where('status', 'paid')
                ->whereMonth('payment_date', $thisMonth)
                ->whereYear('payment_date', $thisYear)
                ->whereHas('contract', fn ($q) => $q->where('employee_id', $employee->id))
                ->with('contract')
                ->get();
            $collectedThisMonth = $paymentsThisMonth->sum(fn($p) => $this->paymentToUsd($p, (float)$p->amount));

            // ── Previous month ─────────────────────────
            $contractsPrevMonth = Contract::where('employee_id', $employee->id)
                ->where(function ($q) use ($prevMonth, $prevYear) {
                    $q->whereMonth('start_date', $prevMonth)->whereYear('start_date', $prevYear)
                      ->orWhere(function ($sq) use ($prevMonth, $prevYear) {
                          $sq->whereNull('start_date')->whereMonth('created_at', $prevMonth)->whereYear('created_at', $prevYear);
                      });
                })->get();
            $salesPrevMonth = $contractsPrevMonth->sum(fn($c) => $this->contractToUsd($c, (float)$c->contract_value));

            $paymentsPrevMonth = Payment::where('status', 'paid')
                ->whereMonth('payment_date', $prevMonth)
                ->whereYear('payment_date', $prevYear)
                ->whereHas('contract', fn ($q) => $q->where('employee_id', $employee->id))
                ->with('contract')
                ->get();
            $collectedPrevMonth = $paymentsPrevMonth->sum(fn($p) => $this->paymentToUsd($p, (float)$p->amount));

            // ── Totals ─────────────────────────────────
            $contractsTotal = Contract::where('employee_id', $employee->id)
                ->where(function ($q) use ($year) {
                    $q->whereYear('start_date', $year)
                      ->orWhere(function ($sq) use ($year) {
                          $sq->whereNull('start_date')->whereYear('created_at', $year);
                      });
                })->get();
            $totalSales = $contractsTotal->sum(fn($c) => $this->contractToUsd($c, (float)$c->contract_value));

            $paymentsTotal = Payment::where('status', 'paid')
                ->whereYear('payment_date', $year)
                ->whereHas('contract', fn ($q) => $q->where('employee_id', $employee->id))
                ->with('contract')
                ->get();
            $totalCollected = $paymentsTotal->sum(fn($p) => $this->paymentToUsd($p, (float)$p->amount));

            $data[] = [
                'name'                  => $employee->name,
                // This month
                'contracts_this_month'  => $contractsThisMonth->count(),
                'sales_this_month'      => round($salesThisMonth, 2),
                'collected_this_month'  => round($collectedThisMonth, 2),
                // Previous month
                'contracts_prev_month'  => $contractsPrevMonth->count(),
                'sales_prev_month'      => round($salesPrevMonth, 2),
                'collected_prev_month'  => round($collectedPrevMonth, 2),
                // Totals
                'total_contracts'       => $contractsTotal->count(),
                'total_sales'           => round($totalSales, 2),
                'total_collected'       => round($totalCollected, 2),
            ];
        }

        return $data;
    }

    /**
     * Fill all 12 months with zero-default values.
     */
    private function fillMonthlyData(array $data): array
    {
        $filled = [];
        for ($m = 1; $m <= 12; $m++) {
            $filled[$m] = round((float) ($data[$m] ?? 0), 2);
        }
        return $filled;
    }
}
