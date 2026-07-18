<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ContractService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Requests\RenewContractRequest;
use App\Http\Resources\ContractCollection;
use App\Http\Resources\ContractResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD operations for contract management.
 */
class ContractController extends Controller
{
    public function __construct(private ContractService $service) {}

    public function index(Request $request): JsonResponse
    {
        $contracts = $this->service->list($request->all());
        return ApiResponse::paginated(new ContractCollection($contracts));
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $contract = $this->service->create($request->validated());
        return ApiResponse::created(new ContractResource($contract), 'Contract created.');
    }

    public function show(int $id): JsonResponse
    {
        $contract = $this->service->find($id);
        return ApiResponse::success(new ContractResource($contract));
    }

    public function update(UpdateContractRequest $request, int $id): JsonResponse
    {
        $contract = $this->service->update($id, $request->validated());
        return ApiResponse::success(new ContractResource($contract), 'Contract updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->service->delete($id);
        if (!$deleted) {
            return ApiResponse::conflict('Cannot delete active or signed contract.', 'CONTRACT_CANNOT_BE_DELETED');
        }
        return ApiResponse::success(null, 'Contract deleted.');
    }

    public function renew(RenewContractRequest $request, int $id): JsonResponse
    {
        $contract = $this->service->renewContract($id, $request->validated());
        return ApiResponse::created(new ContractResource($contract), 'Contract renewed successfully.');
    }

    public function tree(int $id): JsonResponse
    {
        $tree = $this->service->getTree($id);
        return ApiResponse::success(new ContractResource($tree));
    }
}
