<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ContactService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * CRUD operations for contacts nested under companies.
 */
class ContactController extends Controller
{
    public function __construct(private ContactService $service) {}

    public function index(int $companyId): JsonResponse
    {
        $contacts = $this->service->listByCompany($companyId);
        return ApiResponse::success(ContactResource::collection($contacts));
    }

    public function store(StoreContactRequest $request, int $companyId): JsonResponse
    {
        $contact = $this->service->create($companyId, $request->validated());
        return ApiResponse::created(new ContactResource($contact), 'Contact created.');
    }

    public function update(UpdateContactRequest $request, int $companyId, int $contactId): JsonResponse
    {
        $contact = $this->service->update($contactId, $request->validated());
        return ApiResponse::success(new ContactResource($contact), 'Contact updated.');
    }

    public function destroy(int $companyId, int $contactId): JsonResponse
    {
        $this->service->delete($contactId);
        return ApiResponse::success(null, 'Contact deleted.');
    }
}
