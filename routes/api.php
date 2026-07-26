<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\WeeklyReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
| All routes prefixed with /api/v1/ (configured in bootstrap/app.php).
|--------------------------------------------------------------------------
*/

// CORS Preflight Fallback Route
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', request()->header('Origin', '*'))
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, Application')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('any', '.*');

// Public auth
Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Employees
    Route::match(['put', 'patch', 'post'], 'employees/{employee}', [EmployeeController::class, 'update']);
    Route::apiResource('employees', EmployeeController::class);
    Route::get('employees/{employee}/stats', [EmployeeController::class, 'stats'])->name('employees.stats');

    // Companies
    Route::apiResource('companies', CompanyController::class);

    // Contacts (nested under companies)
    Route::get('companies/{company}/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('companies/{company}/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::put('companies/{company}/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('companies/{company}/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    // Services
    Route::apiResource('services', ServiceController::class);

    // Contracts
    Route::apiResource('contracts', ContractController::class);
    Route::post('contracts/{contract}/renew', [ContractController::class, 'renew'])->name('contracts.renew');
    Route::get('contracts/{contract}/tree', [ContractController::class, 'tree'])->name('contracts.tree');

    // Payments (nested under contracts)
    Route::get('contracts/{contract}/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('contracts/{contract}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::put('contracts/{contract}/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('contracts/{contract}/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // Attachments
    Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Search
    Route::get('search', [SearchController::class, 'index'])->name('search');

    // Reports
    Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('reports/yearly', [ReportController::class, 'yearly'])->name('reports.yearly');
    Route::apiResource('weekly-reports', WeeklyReportController::class)->except(['update']);

    // Export
    Route::get('export/contracts', [\App\Http\Controllers\Api\V1\ExportController::class, 'contracts'])->name('export.contracts');
    Route::get('export/payments', [\App\Http\Controllers\Api\V1\ExportController::class, 'payments'])->name('export.payments');
    Route::get('export/report', [\App\Http\Controllers\Api\V1\ExportController::class, 'report'])->name('export.report');

    // ─── Social Media Module ───
    Route::prefix('sm')->group(function () {
        $ctrl = \App\Http\Controllers\Api\V1\SocialMedia\SocialMediaController::class;

        // Packages
        Route::get('packages', [$ctrl, 'listPackages'])->name('sm.packages.index');
        Route::post('packages', [$ctrl, 'storePackage'])->name('sm.packages.store');
        Route::put('packages/{id}', [$ctrl, 'updatePackage'])->name('sm.packages.update');
        Route::delete('packages/{id}', [$ctrl, 'deletePackage'])->name('sm.packages.destroy');

        // Content Plans
        Route::get('plans', [$ctrl, 'listPlans'])->name('sm.plans.index');
        Route::post('plans', [$ctrl, 'storePlan'])->name('sm.plans.store');
        Route::post('plans/batch', [$ctrl, 'storePlanWithItems'])->name('sm.plans.batch');
        Route::get('plans/{id}', [$ctrl, 'showPlan'])->name('sm.plans.show');
        Route::put('plans/{id}', [$ctrl, 'updatePlan'])->name('sm.plans.update');
        Route::delete('plans/{id}', [$ctrl, 'deletePlan'])->name('sm.plans.destroy');

        // Content Items
        Route::get('items', [$ctrl, 'listItems'])->name('sm.items.index');
        Route::post('items', [$ctrl, 'storeItem'])->name('sm.items.store');
        Route::put('items/{id}', [$ctrl, 'updateItem'])->name('sm.items.update');
        Route::patch('items/{id}/toggle', [$ctrl, 'toggleCheckboxes'])->name('sm.items.toggle');
        Route::delete('items/{id}', [$ctrl, 'deleteItem'])->name('sm.items.destroy');

        // Photo Sessions
        Route::get('sessions', [$ctrl, 'listSessions'])->name('sm.sessions.index');
        Route::post('sessions', [$ctrl, 'storeSession'])->name('sm.sessions.store');
        Route::put('sessions/{id}', [$ctrl, 'updateSession'])->name('sm.sessions.update');
        Route::patch('sessions/{id}/status', [$ctrl, 'updateSessionStatus'])->name('sm.sessions.status');
        Route::delete('sessions/{id}', [$ctrl, 'deleteSession'])->name('sm.sessions.destroy');

        // Dashboard, Workload & Calendar
        Route::get('alerts', [$ctrl, 'alerts'])->name('sm.alerts');
        Route::get('dashboard', [$ctrl, 'dashboard'])->name('sm.dashboard');
        Route::get('workload', [$ctrl, 'workload'])->name('sm.workload');
        Route::get('calendar', [$ctrl, 'calendar'])->name('sm.calendar');
    });
});

