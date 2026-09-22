<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServerSubscriptionResource;
use App\Http\Responses\ApiResponse;
use App\Models\ServerSubscription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerSubscriptionController extends Controller
{
    /**
     * Dashboard KPI statistics and breakdown.
     */
    public function dashboard(): JsonResponse
    {
        $today = Carbon::today();
        $thirtyDays = Carbon::today()->addDays(30);

        $total = ServerSubscription::count();
        $active = ServerSubscription::where('status', 'active')
            ->whereDate('end_date', '>=', $today)
            ->count();
        $expiringSoon = ServerSubscription::where('status', '!=', 'cancelled')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $thirtyDays)
            ->count();
        $expired = ServerSubscription::where('status', '!=', 'cancelled')
            ->whereDate('end_date', '<', $today)
            ->count();

        // Group by type
        $byType = [
            'vps' => ServerSubscription::where('type', 'vps')->count(),
            'hosting' => ServerSubscription::where('type', 'hosting')->count(),
            'domain' => ServerSubscription::where('type', 'domain')->count(),
            'email' => ServerSubscription::where('type', 'email')->count(),
            'ssl' => ServerSubscription::where('type', 'ssl')->count(),
        ];

        // Total cost
        $totalCost = (float) ServerSubscription::sum('cost');

        // Unique companies
        $companies = ServerSubscription::whereNotNull('company_name')
            ->where('company_name', '!=', '')
            ->distinct()
            ->pluck('company_name')
            ->values();

        return ApiResponse::success([
            'total' => $total,
            'active' => $active,
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
            'by_type' => $byType,
            'total_cost' => $totalCost,
            'companies' => $companies,
        ]);
    }

    /**
     * Paginated list of subscriptions with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServerSubscription::query();

        // Filter by type
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filter by company
        if ($request->filled('company_name') && $request->company_name !== 'all') {
            $query->where('company_name', $request->company_name);
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $today = Carbon::today();
            if ($request->status === 'active') {
                $query->where('status', 'active')->whereDate('end_date', '>=', $today);
            } elseif ($request->status === 'expiring_soon') {
                $query->where('status', '!=', 'cancelled')
                    ->whereDate('end_date', '>=', $today)
                    ->whereDate('end_date', '<=', Carbon::today()->addDays(30));
            } elseif ($request->status === 'expired') {
                $query->where('status', '!=', 'cancelled')
                    ->whereDate('end_date', '<', $today);
            } else {
                $query->where('status', $request->status);
            }
        }

        // Search
        if ($request->filled('search')) {
            $s = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                    ->orWhere('domain', 'like', $s)
                    ->orWhere('company_name', 'like', $s)
                    ->orWhere('provider', 'like', $s)
                    ->orWhere('notes', 'like', $s);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'end_date');
        $sortOrder = $request->get('sort_order', 'asc');
        $allowedSorts = ['name', 'company_name', 'type', 'end_date', 'start_date', 'cost', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('end_date', 'asc');
        }

        $perPage = (int) $request->get('per_page', 25);
        $paginated = $query->paginate($perPage);

        return ApiResponse::paginated(
            ServerSubscriptionResource::collection($paginated)
        );
    }

    /**
     * Store a new subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'type' => 'required|string|in:vps,hosting,domain,email,ssl',
            'domain' => 'nullable|string|max:255',
            'provider' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'start_date' => 'nullable|date',
            'end_date' => 'required|date',
            'status' => 'nullable|string|in:active,expiring_soon,expired,cancelled',
            'notes' => 'nullable|string',
        ]);

        $sub = ServerSubscription::create([
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'type' => $validated['type'],
            'domain' => $validated['domain'] ?? null,
            'provider' => $validated['provider'] ?? 'GoDaddy',
            'cost' => $validated['cost'] ?? 0,
            'currency' => $validated['currency'] ?? 'USD',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'],
            'status' => $validated['status'] ?? 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::created(
            new ServerSubscriptionResource($sub),
            'Subscription created successfully.'
        );
    }

    /**
     * Show a single subscription.
     */
    public function show(ServerSubscription $serverSubscription): JsonResponse
    {
        return ApiResponse::success(
            new ServerSubscriptionResource($serverSubscription)
        );
    }

    /**
     * Update a subscription.
     */
    public function update(Request $request, ServerSubscription $serverSubscription): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'type' => 'sometimes|required|string|in:vps,hosting,domain,email,ssl',
            'domain' => 'nullable|string|max:255',
            'provider' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'start_date' => 'nullable|date',
            'end_date' => 'sometimes|required|date',
            'status' => 'nullable|string|in:active,expiring_soon,expired,cancelled',
            'notes' => 'nullable|string',
        ]);

        $serverSubscription->update($validated);

        return ApiResponse::success(
            new ServerSubscriptionResource($serverSubscription),
            'Subscription updated successfully.'
        );
    }

    /**
     * Delete a subscription.
     */
    public function destroy(ServerSubscription $serverSubscription): JsonResponse
    {
        $serverSubscription->delete();

        return ApiResponse::success(null, 'Subscription deleted successfully.');
    }

    /**
     * Renew a subscription with new end_date & cost.
     */
    public function renew(Request $request, ServerSubscription $serverSubscription): JsonResponse
    {
        $validated = $request->validate([
            'end_date' => 'required|date',
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $newEndDate = Carbon::parse($validated['end_date']);
        $newStartDate = $serverSubscription->end_date ? Carbon::parse($serverSubscription->end_date) : Carbon::today();

        $serverSubscription->update([
            'start_date' => $newStartDate->format('Y-m-d'),
            'end_date' => $newEndDate->format('Y-m-d'),
            'cost' => $validated['cost'] ?? $serverSubscription->cost,
            'status' => 'active',
            'notes' => $validated['notes'] ?? $serverSubscription->notes,
        ]);

        return ApiResponse::success(
            new ServerSubscriptionResource($serverSubscription),
            'Subscription renewed successfully.'
        );
    }
}
