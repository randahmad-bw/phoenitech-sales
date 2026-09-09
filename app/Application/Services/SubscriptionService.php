<?php

namespace App\Application\Services;

use App\Helpers\CurrencyHelper;
use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Handles subscription/renewal dashboard stats and filtered listing.
 * A "subscription" is any contract with an end_date (time-bound, renewable).
 * Re-uses the existing contracts table — no separate subscriptions table needed.
 */
class SubscriptionService
{
    /**
     * Build the subscriptions dashboard KPI stats.
     */
    public function getDashboard(array $filters = []): array
    {
        $baseQuery = Contract::subscriptions()->with('payments');

        if (!empty($filters['product']) && $filters['product'] !== 'all') {
            $baseQuery->byProduct($filters['product']);
        }

        if (!empty($filters['year'])) {
            $year = (int) $filters['year'];
            $baseQuery->where(function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                  ->orWhereYear('end_date', $year)
                  ->orWhere(function ($sq) use ($year) {
                      $sq->whereYear('start_date', '<=', $year)
                         ->whereYear('end_date', '>=', $year);
                  });
            });
        }

        $contracts = $baseQuery->get();

        $active = $contracts->where('status', 'active');
        $expired = $contracts->filter(fn($c) => 
            $c->end_date && $c->end_date->isPast() && !in_array($c->status, ['completed', 'cancelled'])
        );
        $expiringSoon = $contracts->filter(fn($c) => 
            $c->status === 'active' && 
            $c->end_date && 
            $c->end_date->isFuture() && 
            $c->end_date->diffInDays(now()) <= 30
        );
        $renewed = $contracts->whereNotNull('parent_contract_id');

        // Renewal rate = renewed / (expired + renewed) * 100
        $expiredAndRenewable = $contracts->filter(fn($c) => 
            $c->end_date && $c->end_date->isPast()
        );
        $renewedFromExpired = $contracts->whereNotNull('parent_contract_id');
        $renewalRate = $expiredAndRenewable->count() > 0 
            ? round(($renewedFromExpired->count() / $expiredAndRenewable->count()) * 100, 2)
            : 0;

        // Monthly recurring revenue (sum of active contracts value / their duration in months)
        $mrr = 0;
        foreach ($active as $c) {
            $months = $c->start_date && $c->end_date 
                ? max(1, $c->start_date->diffInMonths($c->end_date))
                : 12;
            $mrr += $this->toUsd($c, (float) $c->contract_value) / $months;
        }

        $totalValue = $active->sum(fn($c) => $this->toUsd($c, (float) $c->contract_value));

        // Per-product breakdown
        $byProduct = [];
        foreach (['phoenitech', 'onocode'] as $product) {
            $productContracts = $contracts->where('product', $product);
            $productActive = $productContracts->where('status', 'active');
            $productExpired = $productContracts->filter(fn($c) => 
                $c->end_date && $c->end_date->isPast() && !in_array($c->status, ['completed', 'cancelled'])
            );
            $byProduct[$product] = [
                'active'  => $productActive->count(),
                'expired' => $productExpired->count(),
                'value'   => round($productActive->sum(fn($c) => $this->toUsd($c, (float) $c->contract_value)), 2),
            ];
        }

        return [
            'total_active'              => $active->count(),
            'expiring_soon'             => $expiringSoon->count(),
            'expired'                   => $expired->count(),
            'renewal_rate'              => $renewalRate,
            'monthly_recurring_revenue' => round($mrr, 2),
            'total_value'               => round($totalValue, 2),
            'renewed_this_month'        => $renewed->filter(fn($c) =>
                $c->created_at && $c->created_at->month === now()->month && $c->created_at->year === now()->year
            )->count(),
            'by_product'                => $byProduct,
        ];
    }

    /**
     * Retrieve paginated subscription list with filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 25);

        $query = Contract::subscriptions()
            ->with(['company', 'employee', 'service'])
            ->withCount('renewals');

        // Product filter
        if (!empty($filters['product']) && $filters['product'] !== 'all') {
            $query->byProduct($filters['product']);
        }

        // Status filter (subscription-specific statuses)
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'active':
                    $query->where('status', 'active')
                          ->where(function ($q) {
                              $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', now()->toDateString());
                          });
                    break;
                case 'expired':
                    $query->where('end_date', '<', now()->toDateString())
                          ->whereNotIn('status', ['completed', 'cancelled']);
                    break;
                case 'expiring_soon':
                    $query->expiringSoon(30);
                    break;
                case 'cancelled':
                    $query->where('status', 'cancelled');
                    break;
                default:
                    $query->where('status', $filters['status']);
            }
        }

        // Employee filter
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // Company filter
        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        // Service filter
        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $query->whereDate('end_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('end_date', '<=', $filters['date_to']);
        }

        // Year filter
        if (!empty($filters['year'])) {
            $query->where(function ($q) use ($filters) {
                $year = (int) $filters['year'];
                $q->whereYear('start_date', $year)
                  ->orWhereYear('end_date', $year);
            });
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                  ->orWhereHas('company', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderByRaw("
            CASE 
                WHEN status = 'active' AND end_date IS NOT NULL AND end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 0
                WHEN status = 'active' THEN 1
                WHEN end_date < CURDATE() THEN 2
                ELSE 3
            END ASC
        ")
        ->orderBy('end_date', 'asc')
        ->paginate($perPage);
    }

    /**
     * Convert a contract amount to USD using its stored exchange rate.
     */
    private function toUsd(Contract $contract, float $amount): float
    {
        if ($contract->currency === 'USD') {
            return $amount;
        }
        $rate = $contract->exchange_rate;
        if ($rate && (float) $rate > 0 && (float) $rate !== 1.0) {
            return $amount / (float) $rate;
        }
        return CurrencyHelper::toUsd($amount, $contract->currency);
    }
}
