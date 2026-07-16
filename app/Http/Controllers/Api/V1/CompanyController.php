<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\CompanyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyCollection;
use App\Http\Resources\CompanyResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD operations for company management.
 */
class CompanyController extends Controller
{
    public function __construct(private CompanyService $service) {}

    public function index(Request $request): JsonResponse
    {
        $companies = $this->service->list($request->all());
        return ApiResponse::paginated(new CompanyCollection($companies));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());
        $message = $result['name_exists_warning'] ? 'Company created. Note: a company with this name already exists.' : 'Company created.';
        return ApiResponse::created(new CompanyResource($result['company']), $message);
    }

    public function show(int $id): JsonResponse
    {
        $company = $this->service->find($id);
        return ApiResponse::success(new CompanyResource($company));
    }

    public function update(UpdateCompanyRequest $request, int $id): JsonResponse
    {
        $company = $this->service->update($id, $request->validated());
        return ApiResponse::success(new CompanyResource($company), 'Company updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->service->delete($id);
        if (!$deleted) {
            return ApiResponse::conflict('Cannot delete company with active contracts.', 'COMPANY_HAS_ACTIVE_CONTRACTS');
        }
        return ApiResponse::success(null, 'Company deleted.');
    }
}
