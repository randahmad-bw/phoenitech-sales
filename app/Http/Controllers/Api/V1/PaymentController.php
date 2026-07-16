<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\PaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * CRUD operations for payments nested under contracts.
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $service) {}

    public function index(int $contractId): JsonResponse
    {
        $payments = $this->service->listByContract($contractId);
        return ApiResponse::success(PaymentResource::collection($payments));
    }

    public function store(StorePaymentRequest $request, int $contractId): JsonResponse
    {
        $payment = $this->service->create($contractId, $request->validated());
        return ApiResponse::created(new PaymentResource($payment), 'Payment created.');
    }

    public function update(UpdatePaymentRequest $request, int $contractId, int $paymentId): JsonResponse
    {
        $payment = $this->service->update($paymentId, $request->validated());
        return ApiResponse::success(new PaymentResource($payment), 'Payment updated.');
    }

    public function destroy(int $contractId, int $paymentId): JsonResponse
    {
        $this->service->delete($paymentId);
        return ApiResponse::success(null, 'Payment deleted.');
    }
}
