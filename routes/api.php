<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WeeklyReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
| All routes prefixed with /api/v1/ (configured in bootstrap/app.php).
|--------------------------------------------------------------------------
*/

/*
 * CORS preflight is handled globally by Illuminate\Http\Middleware\HandleCors
 * using config/cors.php. Do not add a catch-all OPTIONS route here — one that
 * echoes back the incoming Origin alongside Allow-Credentials defeats CORS
 * entirely.
 */

// Public auth — throttled per email + IP to block credential stuffing.
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

// Protected routes — authenticated, active account required. Each route further
// declares the permission(s) it needs; a super_admin bypasses all checks.
Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // Auth — available to any authenticated, active user.
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');

    // Dashboard — overview landing, any authenticated user (data is scoped per role).
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ─── Access Control: Users, Roles & Permissions ───
    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:users.view')->name('users.index');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:users.create')->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view')->name('users.show');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.edit')->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete')->name('users.destroy');
    Route::patch('users/{user}/status', [UserController::class, 'setActive'])
        ->middleware('permission:users.activate')->name('users.status');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->middleware('permission:users.edit')->name('users.reset-password');

    Route::get('permissions', [RoleController::class, 'permissions'])
        ->middleware('permission:roles.view')->name('permissions.index');
    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.create')->name('roles.store');
    Route::get('roles/{role}', [RoleController::class, 'show'])
        ->middleware('permission:roles.view')->name('roles.show');
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.edit')->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.delete')->name('roles.destroy');

    // ─── Audit Trail (read-only) ───
    // Static segments come before {auditLog} so "filters" and "record" are not
    // swallowed by the show route's binding.
    Route::get('audit-logs/filters', [AuditLogController::class, 'filters'])
        ->middleware('permission:audit.view')->name('audit-logs.filters');
    Route::get('audit-logs/record/{type}/{id}', [AuditLogController::class, 'forRecord'])
        ->middleware('permission:audit.view')->name('audit-logs.record');
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])
        ->middleware('permission:audit.view')->name('audit-logs.show');

    // Employees & Overall Stats
    Route::get('employees/overall-stats', [EmployeeController::class, 'overallStats'])
        ->middleware('permission:employees.view_all')->name('employees.overall-stats');
    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('permission:employees.view_all|employees.view_own')->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('permission:employees.create')->name('employees.store');
    Route::get('employees/{employee}/stats', [EmployeeController::class, 'stats'])
        ->middleware('permission:employees.view_all|employees.view_own')->name('employees.stats');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])
        ->middleware('permission:employees.view_all|employees.view_own')->name('employees.show');
    Route::match(['put', 'patch', 'post'], 'employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:employees.edit')->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:employees.delete')->name('employees.destroy');

    // Employee Leaves
    Route::get('employees/{employee}/leaves', [\App\Http\Controllers\Api\V1\EmployeeLeaveController::class, 'index'])
        ->middleware('permission:leave.view_all|leave.view_own')->name('employees.leaves.index');
    Route::post('employees/{employee}/leaves', [\App\Http\Controllers\Api\V1\EmployeeLeaveController::class, 'store'])
        ->middleware('permission:leave.create')->name('employees.leaves.store');
    Route::put('employees/{employee}/leaves/{leave}', [\App\Http\Controllers\Api\V1\EmployeeLeaveController::class, 'update'])
        ->middleware('permission:leave.approve|leave.reject')->name('employees.leaves.update');
    Route::delete('employees/{employee}/leaves/{leave}', [\App\Http\Controllers\Api\V1\EmployeeLeaveController::class, 'destroy'])
        ->middleware('permission:leave.approve')->name('employees.leaves.destroy');

    // Employee Overtimes
    Route::get('employees/{employee}/overtimes', [\App\Http\Controllers\Api\V1\EmployeeOvertimeController::class, 'index'])
        ->middleware('permission:overtime.view_all|overtime.view_own')->name('employees.overtimes.index');
    Route::post('employees/{employee}/overtimes', [\App\Http\Controllers\Api\V1\EmployeeOvertimeController::class, 'store'])
        ->middleware('permission:overtime.create')->name('employees.overtimes.store');
    Route::put('employees/{employee}/overtimes/{overtime}', [\App\Http\Controllers\Api\V1\EmployeeOvertimeController::class, 'update'])
        ->middleware('permission:overtime.approve|overtime.reject')->name('employees.overtimes.update');
    Route::delete('employees/{employee}/overtimes/{overtime}', [\App\Http\Controllers\Api\V1\EmployeeOvertimeController::class, 'destroy'])
        ->middleware('permission:overtime.approve')->name('employees.overtimes.destroy');

    // Companies
    Route::get('companies', [CompanyController::class, 'index'])
        ->middleware('permission:companies.view')->name('companies.index');
    Route::post('companies', [CompanyController::class, 'store'])
        ->middleware('permission:companies.create')->name('companies.store');
    Route::get('companies/{company}', [CompanyController::class, 'show'])
        ->middleware('permission:companies.view')->name('companies.show');
    Route::match(['put', 'patch'], 'companies/{company}', [CompanyController::class, 'update'])
        ->middleware('permission:companies.edit')->name('companies.update');
    Route::delete('companies/{company}', [CompanyController::class, 'destroy'])
        ->middleware('permission:companies.delete')->name('companies.destroy');

    // Contacts (nested under companies)
    Route::get('companies/{company}/contacts', [ContactController::class, 'index'])
        ->middleware('permission:companies.view')->name('contacts.index');
    Route::post('companies/{company}/contacts', [ContactController::class, 'store'])
        ->middleware('permission:companies.edit')->name('contacts.store');
    Route::put('companies/{company}/contacts/{contact}', [ContactController::class, 'update'])
        ->middleware('permission:companies.edit')->name('contacts.update');
    Route::delete('companies/{company}/contacts/{contact}', [ContactController::class, 'destroy'])
        ->middleware('permission:companies.edit')->name('contacts.destroy');

    // Services — reference catalog. Reads open to any authenticated user (used by
    // contract forms); writes require settings.edit.
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::post('services', [ServiceController::class, 'store'])
        ->middleware('permission:settings.edit')->name('services.store');
    Route::match(['put', 'patch'], 'services/{service}', [ServiceController::class, 'update'])
        ->middleware('permission:settings.edit')->name('services.update');
    Route::delete('services/{service}', [ServiceController::class, 'destroy'])
        ->middleware('permission:settings.edit')->name('services.destroy');

    // Contracts
    Route::get('contracts', [ContractController::class, 'index'])
        ->middleware('permission:contracts.view_all|contracts.view_own')->name('contracts.index');
    Route::post('contracts', [ContractController::class, 'store'])
        ->middleware('permission:contracts.create')->name('contracts.store');
    Route::get('contracts/{contract}/tree', [ContractController::class, 'tree'])
        ->middleware('permission:contracts.view_all|contracts.view_own')->name('contracts.tree');
    Route::get('contracts/{contract}', [ContractController::class, 'show'])
        ->middleware('permission:contracts.view_all|contracts.view_own')->name('contracts.show');
    Route::match(['put', 'patch'], 'contracts/{contract}', [ContractController::class, 'update'])
        ->middleware('permission:contracts.edit')->name('contracts.update');
    Route::delete('contracts/{contract}', [ContractController::class, 'destroy'])
        ->middleware('permission:contracts.delete')->name('contracts.destroy');
    Route::post('contracts/{contract}/renew', [ContractController::class, 'renew'])
        ->middleware('permission:contracts.renew')->name('contracts.renew');

    // Payments (nested under contracts)
    Route::get('contracts/{contract}/payments', [PaymentController::class, 'index'])
        ->middleware('permission:payments.view')->name('payments.index');
    Route::post('contracts/{contract}/payments', [PaymentController::class, 'store'])
        ->middleware('permission:payments.create')->name('payments.store');
    Route::put('contracts/{contract}/payments/{payment}', [PaymentController::class, 'update'])
        ->middleware('permission:payments.edit')->name('payments.update');
    Route::delete('contracts/{contract}/payments/{payment}', [PaymentController::class, 'destroy'])
        ->middleware('permission:payments.delete')->name('payments.destroy');

    // Attachments — tied to contracts.
    Route::post('attachments', [AttachmentController::class, 'store'])
        ->middleware('permission:contracts.edit')->name('attachments.store');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->middleware('permission:contracts.edit')->name('attachments.destroy');

    // Search — any authenticated user.
    Route::get('search', [SearchController::class, 'index'])->name('search');

    // Reports
    Route::get('reports/monthly', [ReportController::class, 'monthly'])
        ->middleware('permission:reports.view')->name('reports.monthly');
    Route::get('reports/yearly', [ReportController::class, 'yearly'])
        ->middleware('permission:reports.view')->name('reports.yearly');

    // Weekly Reports
    Route::get('weekly-reports', [WeeklyReportController::class, 'index'])
        ->middleware('permission:weekly_reports.view_all|weekly_reports.view_own')->name('weekly-reports.index');
    Route::post('weekly-reports', [WeeklyReportController::class, 'store'])
        ->middleware('permission:weekly_reports.create')->name('weekly-reports.store');
    Route::get('weekly-reports/{weekly_report}', [WeeklyReportController::class, 'show'])
        ->middleware('permission:weekly_reports.view_all|weekly_reports.view_own')->name('weekly-reports.show');
    Route::delete('weekly-reports/{weekly_report}', [WeeklyReportController::class, 'destroy'])
        ->middleware('permission:weekly_reports.delete')->name('weekly-reports.destroy');

    // Export
    Route::get('export/contracts', [\App\Http\Controllers\Api\V1\ExportController::class, 'contracts'])
        ->middleware('permission:reports.export')->name('export.contracts');
    Route::get('export/payments', [\App\Http\Controllers\Api\V1\ExportController::class, 'payments'])
        ->middleware('permission:reports.export')->name('export.payments');
    Route::get('export/report', [\App\Http\Controllers\Api\V1\ExportController::class, 'report'])
        ->middleware('permission:reports.export')->name('export.report');

    // ─── Social Media Module ───
    Route::prefix('sm')->group(function () {
        $ctrl = \App\Http\Controllers\Api\V1\SocialMedia\SocialMediaController::class;

        // Packages
        Route::get('packages', [$ctrl, 'listPackages'])->middleware('permission:social_media.view')->name('sm.packages.index');
        Route::post('packages', [$ctrl, 'storePackage'])->middleware('permission:social_media.create')->name('sm.packages.store');
        Route::put('packages/{id}', [$ctrl, 'updatePackage'])->middleware('permission:social_media.edit')->name('sm.packages.update');
        Route::delete('packages/{id}', [$ctrl, 'deletePackage'])->middleware('permission:social_media.delete')->name('sm.packages.destroy');

        // Content Plans
        Route::get('plans', [$ctrl, 'listPlans'])->middleware('permission:social_media.view')->name('sm.plans.index');
        Route::post('plans', [$ctrl, 'storePlan'])->middleware('permission:social_media.create')->name('sm.plans.store');
        Route::post('plans/batch', [$ctrl, 'storePlanWithItems'])->middleware('permission:social_media.create')->name('sm.plans.batch');
        Route::get('plans/{id}', [$ctrl, 'showPlan'])->middleware('permission:social_media.view')->name('sm.plans.show');
        Route::put('plans/{id}', [$ctrl, 'updatePlan'])->middleware('permission:social_media.edit')->name('sm.plans.update');
        Route::delete('plans/{id}', [$ctrl, 'deletePlan'])->middleware('permission:social_media.delete')->name('sm.plans.destroy');

        // Content Items
        Route::get('items', [$ctrl, 'listItems'])->middleware('permission:social_media.view')->name('sm.items.index');
        Route::post('items', [$ctrl, 'storeItem'])->middleware('permission:social_media.create')->name('sm.items.store');
        Route::put('items/{id}', [$ctrl, 'updateItem'])->middleware('permission:social_media.edit')->name('sm.items.update');
        Route::patch('items/{id}/toggle', [$ctrl, 'toggleCheckboxes'])->middleware('permission:social_media.edit')->name('sm.items.toggle');
        Route::delete('items/{id}', [$ctrl, 'deleteItem'])->middleware('permission:social_media.delete')->name('sm.items.destroy');

        // Photo Sessions
        Route::get('sessions', [$ctrl, 'listSessions'])->middleware('permission:social_media.view')->name('sm.sessions.index');
        Route::post('sessions', [$ctrl, 'storeSession'])->middleware('permission:social_media.create')->name('sm.sessions.store');
        Route::put('sessions/{id}', [$ctrl, 'updateSession'])->middleware('permission:social_media.edit')->name('sm.sessions.update');
        Route::patch('sessions/{id}/status', [$ctrl, 'updateSessionStatus'])->middleware('permission:social_media.edit')->name('sm.sessions.status');
        Route::delete('sessions/{id}', [$ctrl, 'deleteSession'])->middleware('permission:social_media.delete')->name('sm.sessions.destroy');

        // Dashboard, Workload & Calendar
        Route::get('alerts', [$ctrl, 'alerts'])->middleware('permission:social_media.view')->name('sm.alerts');
        Route::get('dashboard', [$ctrl, 'dashboard'])->middleware('permission:social_media.view')->name('sm.dashboard');
        Route::get('workload', [$ctrl, 'workload'])->middleware('permission:social_media.view')->name('sm.workload');
        Route::get('calendar', [$ctrl, 'calendar'])->middleware('permission:social_media.view')->name('sm.calendar');
    });

    // ─── Server Subscriptions Module (Completely separate from Contracts) ───
    Route::prefix('subscriptions')->group(function () {
        $ctrl = \App\Http\Controllers\Api\V1\ServerSubscriptionController::class;
        Route::get('dashboard', [$ctrl, 'dashboard'])->middleware('permission:subscriptions.view')->name('subscriptions.dashboard');
        Route::get('/', [$ctrl, 'index'])->middleware('permission:subscriptions.view')->name('subscriptions.index');
        Route::post('/', [$ctrl, 'store'])->middleware('permission:subscriptions.create')->name('subscriptions.store');
        Route::get('{serverSubscription}', [$ctrl, 'show'])->middleware('permission:subscriptions.view')->name('subscriptions.show');
        Route::put('{serverSubscription}', [$ctrl, 'update'])->middleware('permission:subscriptions.edit')->name('subscriptions.update');
        Route::delete('{serverSubscription}', [$ctrl, 'destroy'])->middleware('permission:subscriptions.delete')->name('subscriptions.destroy');
        Route::post('{serverSubscription}/renew', [$ctrl, 'renew'])->middleware('permission:subscriptions.renew')->name('subscriptions.renew');
    });
});

