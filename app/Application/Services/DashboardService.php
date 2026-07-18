<?php

namespace App\Application\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates dashboard statistics and chart data.
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
     * Calculate overview KPI stats.
     */
    private function getOverviewStats(int $year): array
    {
        $contracts = Contract::whereYear('start_date', $year)->get();
        $totalValue = $contracts->sum('contract_value');
        $totalPaid = Payment::where('status', 'paid')->whereYear('payment_date', $year)->sum('amount');
        $thisMonth = now()->month;

        return [
            'total_companies' => Company::whereYear('created_at', '<=', $year)->count(),
            'total_contacts' => DB::table('contacts')->whereYear('created_at', '<=', $year)->count(),
            'total_contracts' => $contracts->count(),
            'active_contracts' => $contracts->where('status', 'active')->count(),
            'completed_contracts' => $contracts->where('status', 'completed')->count(),
            'cancelled_contracts' => $contracts->where('status', 'cancelled')->count(),
            'expired_contracts' => Contract::whereYear('start_date', $year)->where('end_date', '<', now())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'total_contract_value' => round($totalValue, 2),
            'total_paid' => round($totalPaid, 2),
            'total_remaining' => round($totalValue - $totalPaid, 2),
            'collection_percentage' => $totalValue > 0 ? round(($totalPaid / $totalValue) * 100, 2) : 0,
            'avg_contract_value' => $contracts->count() > 0 ? round($totalValue / $contracts->count(), 2) : 0,
            'largest_contract' => round($contracts->max('contract_value') ?? 0, 2),
            'new_contracts_this_month' => Contract::whereMonth('start_date', $thisMonth)->whereYear('start_date', $year)->count(),
            'new_companies_this_month' => Company::whereMonth('created_at', $thisMonth)->whereYear('created_at', $year)->count(),
            'sales_this_month'         => round(Contract::whereMonth('start_date', $thisMonth)->whereYear('start_date', $year)->sum('contract_value'), 2),
            'total_sales'              => round($totalValue, 2),
            'collected_this_month'     => round(Payment::where('status', 'paid')->whereMonth('payment_date', $thisMonth)->whereYear('payment_date', $year)->sum('amount'), 2),
        ];
    }

    /**
     * Generate monthly sales chart data for a given year.
     */
    private function getMonthlySalesChart(int $year): array
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', start_date) AS INTEGER)" : "MONTH(start_date)";

        $data = Contract::selectRaw("{$monthExpr} as month, SUM(contract_value) as total")
            ->whereYear('start_date', $year)
            ->groupByRaw($monthExpr)
            ->pluck('total', 'month')
            ->toArray();

        return $this->fillMonthlyData($data);
    }

    /**
     * Generate monthly collections chart data for a given year.
     */
    private function getMonthlyCollectionsChart(int $year): array
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', payment_date) AS INTEGER)" : "MONTH(payment_date)";

        $data = Payment::selectRaw("{$monthExpr} as month, SUM(amount) as total")
            ->where('status', 'paid')
            ->whereYear('payment_date', $year)
            ->groupByRaw($monthExpr)
            ->pluck('total', 'month')
            ->toArray();

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
        return Contract::selectRaw('employee_id, SUM(contract_value) as total')
            ->with('employee:id,name')
            ->whereYear('start_date', $year)
            ->whereNotNull('employee_id')
            ->groupBy('employee_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->employee?->name ?? 'Unknown',
                'total' => round($item->total, 2),
            ])
            ->toArray();
    }

    /**
     * Generate top 5 services by total contract value.
     */
    private function getTopServicesChart(int $year): array
    {
        return Contract::selectRaw('service_id, SUM(contract_value) as total, COUNT(*) as count')
            ->with('service:id,name_ar,name_en')
            ->whereYear('start_date', $year)
            ->whereNotNull('service_id')
            ->groupBy('service_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name_ar' => $item->service?->name_ar ?? 'غير معروف',
                'name_en' => $item->service?->name_en ?? 'Unknown',
                'count' => $item->count,
                'total' => round($item->total, 2),
            ])
            ->toArray();
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
                ->whereMonth('start_date', $thisMonth)
                ->whereYear('start_date', $thisYear)
                ->count();

            $salesThisMonth = Contract::where('employee_id', $employee->id)
                ->whereMonth('start_date', $thisMonth)
                ->whereYear('start_date', $thisYear)
                ->sum('contract_value');

            $collectedThisMonth = Payment::where('status', 'paid')
                ->whereMonth('payment_date', $thisMonth)
                ->whereYear('payment_date', $thisYear)
                ->whereHas('contract', fn ($q) => $q->where('employee_id', $employee->id))
                ->sum('amount');

            // ── Previous month ─────────────────────────
            $contractsPrevMonth = Contract::where('employee_id', $employee->id)
                ->whereMonth('start_date', $prevMonth)
                ->whereYear('start_date', $prevYear)
                ->count();

            $salesPrevMonth = Contract::where('employee_id', $employee->id)
                ->whereMonth('start_date', $prevMonth)
                ->whereYear('start_date', $prevYear)
                ->sum('contract_value');

            $collectedPrevMonth = Payment::where('status', 'paid')
                ->whereMonth('payment_date', $prevMonth)
                ->whereYear('payment_date', $prevYear)
                ->whereHas('contract', fn ($q) => $q->where('employee_id', $employee->id))
                ->sum('amount');

            // ── Totals ─────────────────────────────────
            $totalContracts = Contract::where('employee_id', $employee->id)
                ->whereYear('start_date', $year)
                ->count();

            $totalSales = Contract::where('employee_id', $employee->id)
                ->whereYear('start_date', $year)
                ->sum('contract_value');

            $totalCollected = Payment::where('status', 'paid')
                ->whereYear('payment_date', $year)
                ->whereHas('contract', fn ($q) => $q->where('employee_id', $employee->id))
                ->sum('amount');

            $data[] = [
                'name'                  => $employee->name,
                // This month
                'contracts_this_month'  => $contractsThisMonth,
                'sales_this_month'      => round($salesThisMonth, 2),
                'collected_this_month'  => round($collectedThisMonth, 2),
                // Previous month
                'contracts_prev_month'  => $contractsPrevMonth,
                'sales_prev_month'      => round($salesPrevMonth, 2),
                'collected_prev_month'  => round($collectedPrevMonth, 2),
                // Totals
                'total_contracts'       => $totalContracts,
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
