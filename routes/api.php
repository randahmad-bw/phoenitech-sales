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
});
