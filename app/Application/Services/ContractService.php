<?php

namespace App\Application\Services;

use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\Company;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Handles contract CRUD, number generation, and status management.
 */
class ContractService
{
    /**
     * Retrieve paginated contracts with complex filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Contract::with(['company', 'employee', 'service'])
            ->withCount('renewals');

        if (empty($filters['include_renewals']) && empty($filters['search'])) {
            $query->whereNull('parent_contract_id');
        }

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

        $perPage = $filters['per_page'] ?? 100;
        return $query->orderByRaw('case when start_date is null then 1 else 0 end desc')
            ->orderBy('start_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new contract. Resolves company_name to company_id via find-or-create.
     * Optionally creates an initial payment if initial_payment > 0.
     */
    public function create(array $data): Contract
    {
        $initialPayment = $data['initial_payment'] ?? null;
        unset($data['initial_payment']);

        $data = $this->resolveCompany($data);
        $data['contract_number'] = $this->generateContractNumber();

        $contract = Contract::create($data);

        // Record creation history
        ContractHistory::create([
            'contract_id' => $contract->id,
            'field_name'  => 'contract',
            'action'      => 'created',
            'new_value'   => 'Contract created',
        ]);

        // Auto-create the initial payment if provided
        if ($initialPayment && (float) $initialPayment > 0) {
            Payment::create([
                'contract_id'  => $contract->id,
                'amount'       => (float) $initialPayment,
                'exchange_rate'=> $contract->exchange_rate ?? 1.0,
                'payment_date' => $contract->start_date ?? now()->toDateString(),
                'method'       => 'cash',
                'status'       => 'paid',
                'notes'        => 'دفعة أولى عند التوقيع',
            ]);
        }

        return $contract->load(['company', 'employee']);
    }

    /**
     * Find contract by ID with full relations loaded.
     */
    public function find(int $id): Contract
    {
        return Contract::with(['company', 'employee', 'service', 'payments', 'attachments', 'histories'])
            ->findOrFail($id);
    }

    /**
     * Update an existing contract record. Resolves company_name if provided.
     */
    public function update(int $id, array $data): Contract
    {
        $contract = Contract::findOrFail($id);
        $data = $this->resolveCompany($data);

        $original = $contract->getAttributes();
        $contract->fill($data);
        $changes = $contract->getDirty();

        if ($contract->save()) {
            foreach ($changes as $field => $newValue) {
                if ($field === 'updated_at') continue;
                
                ContractHistory::create([
                    'contract_id' => $contract->id,
                    'field_name'  => $field,
                    'old_value'   => $original[$field] ?? null,
                    'new_value'   => $newValue,
                    'action'      => 'updated',
                ]);
            }
        }

        return $contract->fresh()->load(['company', 'employee']);
    }

    /**
     * Resolve company_name to company_id via find-or-create.
     * The company_name key is removed and company_id is injected.
     */
    private function resolveCompany(array $data): array
    {
        if (!empty($data['company_name'])) {
            $company = Company::firstOrCreate(
                ['name' => trim($data['company_name'])],
                ['activity' => null, 'address' => null, 'notes' => null]
            );
            $data['company_id'] = $company->id;
        }
        unset($data['company_name']);
        return $data;
    }

    public function delete(int $id): bool
    {
        $contract = Contract::findOrFail($id);
        return $contract->delete();
    }

    /**
     * Generate the next sequential contract number in CNT-YYYY-NNNN format.
     */
    private function generateContractNumber(): string
    {
        $year = now()->year;
        $prefix = "CNT-{$year}-";

        $lastContract = Contract::where('contract_number', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(contract_number, -4) AS UNSIGNED) DESC")
            ->first();

        if ($lastContract) {
            $lastNumber = (int) substr($lastContract->contract_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Renew an existing contract.
     */
    public function renewContract(int $id, array $data): Contract
    {
        $oldContract = Contract::findOrFail($id);
        
        // Mark old contract as renewed
        $oldContract->update(['status' => 'renewed']);

        // Record renewal history on the old contract
        ContractHistory::create([
            'contract_id' => $oldContract->id,
            'field_name'  => 'status',
            'old_value'   => $oldContract->getOriginal('status'),
            'new_value'   => 'renewed',
            'action'      => 'renewed',
        ]);

        // Replicate basic details to the new contract
        $newContract = clone $oldContract;
        $newContract->exists = false;
        $newContract->id = null;
        $newContract->created_at = null;
        $newContract->updated_at = null;
        $newContract->contract_number = $this->generateContractNumber();
        
        // Apply renewal data. Link to the ROOT parent to maintain a flat hierarchy.
        $newContract->parent_contract_id = $oldContract->getRootParentId();
        $newContract->start_date = $data['start_date'];
        $newContract->end_date = $data['end_date'];
        $newContract->contract_value = $data['contract_value'];
        $newContract->exchange_rate = $data['exchange_rate'] ?? $oldContract->exchange_rate ?? 1.0;
        $newContract->notes = $data['notes'] ?? null;
        $newContract->status = 'active';
        $newContract->progress_percentage = 0;
        
        $newContract->save();

        if (isset($data['initial_payment']) && (float) $data['initial_payment'] > 0) {
            \App\Models\Payment::create([
                'contract_id'  => $newContract->id,
                'amount'       => (float) $data['initial_payment'],
                'exchange_rate'=> $newContract->exchange_rate ?? 1.0,
                'payment_date' => $newContract->start_date ?? now()->toDateString(),
                'method'       => 'cash',
                'status'       => 'paid',
                'notes'        => 'دفعة عند تجديد العقد',
            ]);
        }

        // Record creation history for the new contract
        ContractHistory::create([
            'contract_id' => $newContract->id,
            'field_name'  => 'contract',
            'action'      => 'created',
            'new_value'   => "Renewed from {$oldContract->contract_number}",
        ]);

        return $newContract->load(['company', 'employee']);
    }

    /**
     * Get the full tree of a contract (Root parent + all renewals + histories).
     */
    public function getTree(int $id): Contract
    {
        $contract = Contract::findOrFail($id);
        $rootId = $contract->getRootParentId();
        
        return Contract::with([
            'company', 
            'employee', 
            'service', 
            'histories', 
            'renewals' => function ($q) {
                $q->with(['histories', 'employee']);
            }
        ])->findOrFail($rootId);
    }
}
