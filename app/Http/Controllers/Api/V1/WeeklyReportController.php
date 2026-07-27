<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWeeklyReportRequest;
use App\Http\Resources\WeeklyReportCollection;
use App\Http\Resources\WeeklyReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\WeeklyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller handling Weekly Report management.
 */
class WeeklyReportController extends Controller
{
    /**
     * Display a listing of weekly reports.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employee = $user->employee;

        $query = WeeklyReport::with('employee');

        if ($employee) {
            // Employees see only their own reports
            $query->where('employee_id', $employee->id);
        } else {
            // Admins can see all, with filters
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->integer('employee_id'));
            }
            if ($request->has('week_start_date')) {
                $query->where('week_start_date', $request->input('week_start_date'));
            }
        }

        $perPage = $request->integer('per_page', 25);
        $reports = $query->orderBy('week_start_date', 'desc')->paginate($perPage);

        return ApiResponse::paginated(new WeeklyReportCollection($reports), 'Weekly reports retrieved.');
    }

    /**
     * Store a newly submitted weekly report.
     */
    public function store(StoreWeeklyReportRequest $request): JsonResponse
    {
        $user = Auth::user();
        $employee = $user->employee;

        if (!$employee) {
            return ApiResponse::forbidden('Only employees can submit weekly reports.');
        }

        $validated = $request->validated();
        $weekStartDate = $validated['week_start_date'];

        // Prevent duplicate reports for the same week and employee
        $exists = WeeklyReport::where('employee_id', $employee->id)
            ->where('week_start_date', $weekStartDate)
            ->exists();

        if ($exists) {
            return ApiResponse::conflict('لقد قمت بتقديم تقرير لهذا الأسبوع بالفعل.', 'REPORT_ALREADY_SUBMITTED');
        }

        $report = WeeklyReport::create([
            'employee_id' => $employee->id,
            'week_start_date' => $weekStartDate,
            'kpis' => $validated['kpis'],
            'pipeline' => $validated['pipeline'],
            'next_plan' => $validated['next_plan'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::created(
            new WeeklyReportResource($report->load('employee')),
            'Weekly report submitted successfully.'
        );
    }

    /**
     * Display a specific weekly report.
     */
    public function show(int $id): JsonResponse
    {
        $report = WeeklyReport::with('employee')->findOrFail($id);
        $user = Auth::user();
        $employee = $user->employee;

        // Employees can only view their own reports
        if ($employee && $report->employee_id !== $employee->id) {
            return ApiResponse::forbidden('You are not authorized to view this report.');
        }

        return ApiResponse::success(new WeeklyReportResource($report), 'Weekly report retrieved.');
    }

    /**
     * Remove a weekly report. (Admin only)
     */
    public function destroy(int $id): JsonResponse
    {
        $report = WeeklyReport::findOrFail($id);
        $user = Auth::user();

        if ($user->employee) {
            return ApiResponse::forbidden('Only administrators can delete weekly reports.');
        }

        $report->delete();

        return ApiResponse::success(null, 'Weekly report deleted successfully.');
    }
}
