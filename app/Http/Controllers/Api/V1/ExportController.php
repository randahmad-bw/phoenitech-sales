<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ExportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint controller for data exports (PDF / Excel / CSV).
 */
class ExportController extends Controller
{
    public function __construct(private ExportService $exportService) {}

    /**
     * Export contracts to the selected format.
     */
    public function contracts(ExportRequest $request): Response
    {
        return $this->exportService->exportContracts(
            $request->validated(),
            $request->input('format')
        );
    }

    /**
     * Export payments to the selected format.
     */
    public function payments(ExportRequest $request): Response
    {
        return $this->exportService->exportPayments(
            $request->validated(),
            $request->input('format')
        );
    }

    /**
     * Export monthly/yearly reports to the selected format.
     */
    public function report(ExportRequest $request): Response
    {
        $type = $request->input('type', 'monthly');
        return $this->exportService->exportReport(
            $type,
            $request->validated(),
            $request->input('format')
        );
    }
}
