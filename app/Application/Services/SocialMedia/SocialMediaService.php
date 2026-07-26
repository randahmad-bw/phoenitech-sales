<?php

namespace App\Application\Services\SocialMedia;

use App\Models\SocialMedia\SmPackage;
use App\Models\SocialMedia\ContentPlan;
use App\Models\SocialMedia\ContentItem;
use App\Models\SocialMedia\PhotoSession;
use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Single service handling all Social Media module operations with auto-contract completion.
 */
class SocialMediaService
{
    // ─── Packages ─────────────────────────────────────────────

    public function listPackages(array $filters = []): Collection
    {
        $query = SmPackage::with('contract.company');

        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function createPackage(array $data): SmPackage
    {
        return SmPackage::create($data);
    }

    public function updatePackage(int $id, array $data): SmPackage
    {
        $package = SmPackage::findOrFail($id);
        $package->update($data);
        return $package->fresh('contract.company');
    }

    public function deletePackage(int $id): void
    {
        SmPackage::findOrFail($id)->delete();
    }

    // ─── Content Plans ────────────────────────────────────────

    public function listPlans(array $filters = []): LengthAwarePaginator
    {
        $query = ContentPlan::with(['contract.company', 'package', 'items.designer', 'items.photoSession'])
            ->withCount('items', 'photoSessions');

        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }
        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('year')->orderByDesc('month')
            ->paginate($filters['per_page'] ?? 100);
    }

    public function findPlan(int $id): ContentPlan
    {
        return ContentPlan::with([
            'contract.company',
            'package',
            'items.designer',
            'items.photoSession',
            'photoSessions.photographer',
            'photoSessions.contentItems',
        ])->findOrFail($id);
    }

    public function createPlan(array $data): ContentPlan
    {
        return ContentPlan::create($data);
    }

    public function createPlanWithItems(array $data): ContentPlan
    {
        $plan = ContentPlan::updateOrCreate(
            [
                'contract_id' => $data['contract_id'],
                'month' => $data['month'] ?? now()->month,
                'year' => $data['year'] ?? now()->year,
            ],
            [
                'company_id' => $data['company_id'],
                'sm_package_id' => $data['sm_package_id'] ?? null,
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]
        );

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $itemData) {
                if (empty($itemData['title'])) continue;
                ContentItem::create([
                    'content_plan_id' => $plan->id,
                    'title' => $itemData['title'],
                    'content_type' => $itemData['content_type'] ?? 'post',
                    'design_date' => $itemData['design_date'] ?? null,
                    'publish_date' => $itemData['publish_date'] ?? null,
                    'assigned_to' => !empty($itemData['assigned_to']) ? $itemData['assigned_to'] : null,
                    'is_designed' => (bool) ($itemData['is_designed'] ?? false),
                    'is_published' => (bool) ($itemData['is_published'] ?? false),
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }
        }

        $this->checkContractCompletion($plan->id);

        return $plan->load(['contract.company', 'items.designer']);
    }

    public function updatePlan(int $id, array $data): ContentPlan
    {
        $plan = ContentPlan::findOrFail($id);
        $plan->update($data);
        return $plan->fresh(['contract.company', 'package']);
    }

    public function deletePlan(int $id): void
    {
        ContentPlan::findOrFail($id)->delete();
    }

    // ─── Content Items ────────────────────────────────────────

    public function listItems(array $filters = []): LengthAwarePaginator
    {
        $query = ContentItem::with(['plan.contract.company', 'designer', 'photoSession']);

        if (!empty($filters['content_plan_id'])) {
            $query->where('content_plan_id', $filters['content_plan_id']);
        }
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }
        if (isset($filters['is_designed'])) {
            $query->where('is_designed', (bool) $filters['is_designed']);
        }
        if (isset($filters['is_published'])) {
            $query->where('is_published', (bool) $filters['is_published']);
        }

        return $query->orderBy('publish_date')
            ->paginate($filters['per_page'] ?? 300);
    }

    public function createItem(array $data): ContentItem
    {
        $item = ContentItem::create($data);
        $this->checkContractCompletion($item->content_plan_id);
        return $item;
    }

    public function updateItem(int $id, array $data): ContentItem
    {
        $item = ContentItem::findOrFail($id);
        $item->update($data);
        $this->checkContractCompletion($item->content_plan_id);
        return $item->fresh(['plan.contract.company', 'designer', 'photoSession']);
    }

    public function toggleCheckboxes(int $id, array $data): ContentItem
    {
        $item = ContentItem::findOrFail($id);
        
        $update = [];
        if (array_key_exists('is_designed', $data)) {
            $update['is_designed'] = (bool) $data['is_designed'];
        }
        if (array_key_exists('is_published', $data)) {
            $update['is_published'] = (bool) $data['is_published'];
            if ($update['is_published']) {
                $update['status'] = 'completed';
            }
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = $data['status'];
        }

        $item->update($update);
        $this->checkContractCompletion($item->content_plan_id);

        return $item->fresh(['plan.contract.company', 'designer']);
    }

    public function deleteItem(int $id): void
    {
        $item = ContentItem::findOrFail($id);
        $planId = $item->content_plan_id;
        $item->delete();
        $this->checkContractCompletion($planId);
    }

    // ─── Auto Contract Completion Helper ───────────────────────

    /**
     * Checks if all items for the content plan are published/completed,
     * and updates the contract status to 'completed' and progress to 100%.
     */
    public function checkContractCompletion(int $planId): void
    {
        $plan = ContentPlan::with(['contract.smPackage', 'items'])->find($planId);
        if (!$plan || !$plan->contract) {
            return;
        }

        $items = $plan->items;
        if ($items->isEmpty()) {
            return;
        }

        $totalItems = $items->count();
        $publishedItems = $items->where('is_published', true)->count();

        // Calculate progress percentage
        $progressPct = $totalItems > 0 ? (int) round(($publishedItems / $totalItems) * 100) : 0;

        $contract = $plan->contract;

        // If ALL items are designed AND published -> mark contract completed!
        $allCompleted = $totalItems > 0 && $publishedItems === $totalItems && $items->where('is_designed', false)->count() === 0;

        if ($allCompleted) {
            $contract->update([
                'status' => 'completed',
                'progress_percentage' => 100,
            ]);
            $plan->update(['status' => 'completed']);
        } else {
            $contract->update([
                'progress_percentage' => $progressPct,
            ]);
        }
    }

    // ─── Photo Sessions ───────────────────────────────────────

    public function listSessions(array $filters = []): LengthAwarePaginator
    {
        $query = PhotoSession::with(['plan.contract', 'company', 'photographer', 'contentItems']);

        if (!empty($filters['content_plan_id'])) {
            $query->where('content_plan_id', $filters['content_plan_id']);
        }
        if (!empty($filters['photographer_id'])) {
            $query->where('photographer_id', $filters['photographer_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('session_date')->orderBy('session_time')
            ->paginate($filters['per_page'] ?? 100);
    }

    public function createSession(array $data): PhotoSession
    {
        return PhotoSession::create($data);
    }

    public function updateSession(int $id, array $data): PhotoSession
    {
        $session = PhotoSession::findOrFail($id);
        $session->update($data);
        return $session->fresh(['plan.contract', 'company', 'photographer', 'contentItems']);
    }

    public function updateSessionStatus(int $id, string $status): PhotoSession
    {
        $session = PhotoSession::findOrFail($id);
        $session->update(['status' => $status]);
        return $session->fresh(['plan.contract', 'company', 'photographer']);
    }

    public function deleteSession(int $id): void
    {
        PhotoSession::findOrFail($id)->delete();
    }

    // ─── Publishing Alerts (Today & Tomorrow) ─────────────────

    public function getPublishingAlerts(): Collection
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        return ContentItem::with(['plan.contract.company', 'designer'])
            ->where('is_published', false)
            ->whereNotNull('publish_date')
            ->whereIn('publish_date', [$today, $tomorrow])
            ->orderBy('publish_date')
            ->get();
    }

    // ─── Dashboard Stats ──────────────────────────────────────

    public function getDashboardStats(): array
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        return [
            'total_items' => ContentItem::count(),
            'pending_design' => ContentItem::where('is_designed', false)->count(),
            'designed' => ContentItem::where('is_designed', true)->count(),
            'published' => ContentItem::where('is_published', true)->count(),
            'alerts_today_tomorrow' => ContentItem::where('is_published', false)
                ->whereNotNull('publish_date')
                ->whereIn('publish_date', [$today, $tomorrow])
                ->count(),
            'active_plans' => ContentPlan::where('status', 'active')->count(),
            'upcoming_sessions' => PhotoSession::where('status', 'scheduled')
                ->where('session_date', '>=', $today)
                ->count(),
        ];
    }
}
