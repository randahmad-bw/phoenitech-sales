<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AttendanceManagementController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeLeaveController;
use App\Http\Controllers\Api\V1\EmployeeOvertimeController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\LeaveTypeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ServerSubscriptionController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\SocialMedia\SocialMediaController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WeeklyReportController;
use App\Http\Controllers\Api\V1\WorkScheduleController;
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

    // Dashboard — company financials (contract values, payments, revenue).
    // Not open to every authenticated account: attendance-only staff have no
    // business reason to see the company's numbers, and they land on the
    // attendance screen instead.
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')->name('dashboard');

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

    // ─── Leave types: the catalogue behind the picker ───
    // Reading the catalogue is not the same act as editing it. Anyone who can
    // file a request needs the list, so `index` rides on `leave.view_own`: it
    // fills the picker and names the type on leave already taken, which is why
    // it carries the switched-off types too, flagged. Configuring which types
    // the company uses is a settings act, and `manage` adds how many records
    // each type holds - management information, not everyone's.
    Route::get('leave-types', [LeaveTypeController::class, 'index'])
        ->middleware('permission:leave.view_own|leave.view_all|settings.view')->name('leave-types.index');
    Route::get('leave-types/manage', [LeaveTypeController::class, 'manage'])
        ->middleware('permission:settings.view')->name('leave-types.manage');
    Route::post('leave-types', [LeaveTypeController::class, 'store'])
        ->middleware('permission:settings.edit')->name('leave-types.store');
    Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])
        ->middleware('permission:settings.edit')->name('leave-types.update');
    Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])
        ->middleware('permission:settings.edit')->name('leave-types.destroy');

    // ─── Leave: requesting, and deciding ───
    // Two separate acts behind two separate permissions. Nothing an employee
    // sends can set a status: approved leave rewrites their own attendance
    // calendar, so granting it must never sit with the person it benefits.
    Route::get('leaves/my', [LeaveRequestController::class, 'my'])
        ->middleware('permission:leave.view_own|leave.view_all')->name('leaves.my');
    Route::post('leaves/my', [LeaveRequestController::class, 'store'])
        ->middleware('permission:leave.create')->name('leaves.my.store');
    Route::delete('leaves/my/{leave}', [LeaveRequestController::class, 'cancel'])
        ->middleware('permission:leave.create')->name('leaves.my.cancel');

    Route::get('leaves', [LeaveRequestController::class, 'index'])
        ->middleware('permission:leave.view_all')->name('leaves.index');
    // The number on the sidebar badge. Declared before `{leave}` routes so the
    // literal segment is never read as an id.
    Route::get('leaves/pending-count', [LeaveRequestController::class, 'pendingCount'])
        ->middleware('permission:leave.approve|leave.view_all')->name('leaves.pending-count');
    Route::patch('leaves/{leave}/review', [LeaveRequestController::class, 'review'])
        ->middleware('permission:leave.approve|leave.reject')->name('leaves.review');
    Route::get('employees/{employee}/leave-requests', [LeaveRequestController::class, 'forEmployee'])
        ->middleware('permission:leave.view_all|leave.view_own')->name('employees.leave-requests');

    // Employee Leaves — the older management-side CRUD. Superseded by the
    // routes above for requesting and deciding; kept for direct entry by
    // management (recording leave taken before the system existed).
    Route::get('employees/{employee}/leaves', [EmployeeLeaveController::class, 'index'])
        ->middleware('permission:leave.view_all|leave.view_own')->name('employees.leaves.index');
    // leave.approve, not leave.create: this endpoint accepts a status, so it
    // is a management tool. Employees use POST leaves/my, which cannot.
    Route::post('employees/{employee}/leaves', [EmployeeLeaveController::class, 'store'])
        ->middleware('permission:leave.approve')->name('employees.leaves.store');
    Route::put('employees/{employee}/leaves/{leave}', [EmployeeLeaveController::class, 'update'])
        ->middleware('permission:leave.approve|leave.reject')->name('employees.leaves.update');
    Route::delete('employees/{employee}/leaves/{leave}', [EmployeeLeaveController::class, 'destroy'])
        ->middleware('permission:leave.approve')->name('employees.leaves.destroy');

    // Employee Overtimes
    Route::get('employees/{employee}/overtimes', [EmployeeOvertimeController::class, 'index'])
        ->middleware('permission:overtime.view_all|overtime.view_own')->name('employees.overtimes.index');
    Route::post('employees/{employee}/overtimes', [EmployeeOvertimeController::class, 'store'])
        ->middleware('permission:overtime.create')->name('employees.overtimes.store');
    Route::put('employees/{employee}/overtimes/{overtime}', [EmployeeOvertimeController::class, 'update'])
        ->middleware('permission:overtime.approve|overtime.reject')->name('employees.overtimes.update');
    Route::delete('employees/{employee}/overtimes/{overtime}', [EmployeeOvertimeController::class, 'destroy'])
        ->middleware('permission:overtime.approve')->name('employees.overtimes.destroy');

    // ─── Attendance: self-service ───
    // These four are the employee's whole world: see today, start, stop, look
    // back. None of them accepts an employee id, a time or a status — the
    // employee comes from the token and the clock from the server, so there is
    // nothing here for a hand-crafted request to tamper with.
    // `attendance.create` covers both punches: nobody is ever granted the right
    // to check in without the right to check out.
    Route::get('attendance/today', [AttendanceController::class, 'today'])
        ->middleware('permission:attendance.view_own|attendance.view_all')->name('attendance.today');
    Route::get('attendance/my', [AttendanceController::class, 'my'])
        ->middleware('permission:attendance.view_own|attendance.view_all')->name('attendance.my');
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])
        ->middleware('permission:attendance.create')->name('attendance.check-in');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])
        ->middleware('permission:attendance.create')->name('attendance.check-out');

    // ─── Attendance: management ───
    // These DO accept an employee, times and a status — which is exactly why
    // they are a separate controller behind separate permissions. Corrections
    // require a reason and are written to the audit trail with the old value.
    // Declared before `attendance/{attendance}` so the literal paths win.
    Route::get('attendance/overview', [AttendanceManagementController::class, 'overview'])
        ->middleware('permission:attendance.view_all')->name('attendance.overview');
    // The week/month view: one row per employee instead of one per day.
    Route::get('attendance/summary', [AttendanceManagementController::class, 'summary'])
        ->middleware('permission:attendance.view_all')->name('attendance.summary');
    Route::get('attendance', [AttendanceManagementController::class, 'index'])
        ->middleware('permission:attendance.view_all')->name('attendance.index');
    Route::post('attendance', [AttendanceManagementController::class, 'store'])
        ->middleware('permission:attendance.edit')->name('attendance.store');
    // Extra work: recorded automatically as a second sitting, then ruled on.
    // Declared before attendance/{attendance} so the literal path wins.
    Route::get('attendance/overtime', [AttendanceManagementController::class, 'pendingOvertime'])
        ->middleware('permission:attendance.view_all')->name('attendance.overtime');
    Route::patch('attendance/sessions/{session}/overtime', [AttendanceManagementController::class, 'reviewOvertime'])
        ->middleware('permission:attendance.approve')->name('attendance.overtime.review');

    Route::match(['put', 'patch'], 'attendance/{attendance}', [AttendanceManagementController::class, 'update'])
        ->middleware('permission:attendance.edit')->name('attendance.update');
    Route::delete('attendance/{attendance}', [AttendanceManagementController::class, 'destroy'])
        ->middleware('permission:attendance.delete')->name('attendance.destroy');

    // One employee's calendar. view_own is accepted so an employee can open
    // their own profile; AccessScope enforces which rows that actually means.
    Route::get('employees/{employee}/attendance', [AttendanceManagementController::class, 'forEmployee'])
        ->middleware('permission:attendance.view_all|attendance.view_own')->name('employees.attendance.index');

    // ─── Attendance: schedules & holidays (admin only) ───
    // The schedule screen works in people, not templates: one row per
    // employee, opened and edited directly.
    Route::get('employee-weeks', [WorkScheduleController::class, 'weeks'])
        ->middleware('permission:attendance.manage_schedules')->name('employee-weeks.index');
    Route::match(['put', 'patch'], 'employees/{employee}/week', [WorkScheduleController::class, 'setWeek'])
        ->middleware('permission:attendance.manage_schedules')->name('employees.week.update');

    Route::get('work-schedules', [WorkScheduleController::class, 'index'])
        ->middleware('permission:attendance.manage_schedules')->name('work-schedules.index');
    Route::post('work-schedules', [WorkScheduleController::class, 'store'])
        ->middleware('permission:attendance.manage_schedules')->name('work-schedules.store');
    Route::get('work-schedules/{workSchedule}', [WorkScheduleController::class, 'show'])
        ->middleware('permission:attendance.manage_schedules')->name('work-schedules.show');
    Route::match(['put', 'patch'], 'work-schedules/{workSchedule}', [WorkScheduleController::class, 'update'])
        ->middleware('permission:attendance.manage_schedules')->name('work-schedules.update');
    Route::delete('work-schedules/{workSchedule}', [WorkScheduleController::class, 'destroy'])
        ->middleware('permission:attendance.manage_schedules')->name('work-schedules.destroy');

    Route::get('employees/{employee}/schedules', [WorkScheduleController::class, 'assignments'])
        ->middleware('permission:attendance.manage_schedules')->name('employees.schedules.index');
    Route::post('employees/{employee}/schedules', [WorkScheduleController::class, 'assign'])
        ->middleware('permission:attendance.manage_schedules')->name('employees.schedules.store');

    Route::get('holidays', [HolidayController::class, 'index'])
        ->middleware('permission:attendance.manage_schedules')->name('holidays.index');
    Route::post('holidays', [HolidayController::class, 'store'])
        ->middleware('permission:attendance.manage_schedules')->name('holidays.store');
    Route::match(['put', 'patch'], 'holidays/{holiday}', [HolidayController::class, 'update'])
        ->middleware('permission:attendance.manage_schedules')->name('holidays.update');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])
        ->middleware('permission:attendance.manage_schedules')->name('holidays.destroy');

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

    // ─── Tasks ───
    // One set of endpoints for both readers: `tasks.view_all` sees the
    // company's work, `tasks.view_own` sees their own, and the controller
    // scopes the rows. `summary` is declared before `{task}` so the word is
    // not read as an id.
    Route::get('tasks', [TaskController::class, 'index'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.index');
    Route::get('tasks/summary', [TaskController::class, 'summary'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.summary');
    // The caller's own open checklist lines, across every task they hold. Feeds
    // the check-out dialog, so it is always "mine" and never a board — and like
    // `summary` it sits above `{task}` so the word is not read as an id.
    Route::get('tasks/checklist', [TaskController::class, 'checklist'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.checklist');
    // The caller's own not-started tasks — the sidebar badge and the strip the
    // landing screen shows before the day begins. Always "mine" for the same
    // reason `checklist` is, and above `{task}` for the same reason `summary` is.
    Route::get('tasks/pending', [TaskController::class, 'pending'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.pending');
    Route::post('tasks', [TaskController::class, 'store'])
        ->middleware('permission:tasks.create')->name('tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.show');
    Route::match(['put', 'patch'], 'tasks/{task}', [TaskController::class, 'update'])
        ->middleware('permission:tasks.edit')->name('tasks.update');
    // Reporting progress is not editing the task: the person doing the work
    // moves it, which is why this sits behind view rather than edit.
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.status');
    Route::post('tasks/{task}/comments', [TaskController::class, 'comment'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.comments.store');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])
        ->middleware('permission:tasks.delete')->name('tasks.destroy');
    // Files on a task — the brief, the design, the signed page. Behind the
    // same permission as the thread rather than `tasks.edit`: attaching the
    // work you were asked for is doing the task, not editing it. The
    // controller still checks the file is yours to touch.
    Route::post('tasks/{task}/attachments', [TaskController::class, 'attach'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.attachments.store');
    Route::delete('tasks/{task}/attachments/{attachment}', [TaskController::class, 'detach'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.attachments.destroy');
    // The checklist inside a task. Behind the thread's permission, not
    // `tasks.edit`: writing down and ticking off the steps of the work you were
    // asked for is doing the task. The controller still scopes each call to the
    // caller's own tasks, and refuses to delete a line they did not write.
    Route::post('tasks/{task}/items', [TaskController::class, 'addItem'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.items.store');
    Route::patch('tasks/{task}/items/{item}', [TaskController::class, 'updateItem'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.items.update');
    Route::delete('tasks/{task}/items/{item}', [TaskController::class, 'deleteItem'])
        ->middleware('permission:tasks.view_all|tasks.view_own')->name('tasks.items.destroy');

    // ─── Notifications ───
    // Every route here reads the caller's own feed and nothing else — the
    // service scopes by the authenticated user, so there is no id to tamper
    // with. `unread-count` and `read-all` are declared before `{notification}`
    // so the words are not read as ids.
    Route::get('notifications', [NotificationController::class, 'index'])
        ->middleware('permission:notifications.view')->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->middleware('permission:notifications.view')->name('notifications.unread-count');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->middleware('permission:notifications.view')->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->middleware('permission:notifications.view')->name('notifications.read');

    // Export
    Route::get('export/contracts', [ExportController::class, 'contracts'])
        ->middleware('permission:reports.export')->name('export.contracts');
    Route::get('export/payments', [ExportController::class, 'payments'])
        ->middleware('permission:reports.export')->name('export.payments');
    Route::get('export/report', [ExportController::class, 'report'])
        ->middleware('permission:reports.export')->name('export.report');

    // ─── Social Media Module ───
    Route::prefix('sm')->group(function () {
        $ctrl = SocialMediaController::class;

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
        $ctrl = ServerSubscriptionController::class;
        Route::get('dashboard', [$ctrl, 'dashboard'])->middleware('permission:subscriptions.view')->name('subscriptions.dashboard');
        Route::get('/', [$ctrl, 'index'])->middleware('permission:subscriptions.view')->name('subscriptions.index');
        Route::post('/', [$ctrl, 'store'])->middleware('permission:subscriptions.create')->name('subscriptions.store');
        Route::get('{serverSubscription}', [$ctrl, 'show'])->middleware('permission:subscriptions.view')->name('subscriptions.show');
        Route::put('{serverSubscription}', [$ctrl, 'update'])->middleware('permission:subscriptions.edit')->name('subscriptions.update');
        Route::delete('{serverSubscription}', [$ctrl, 'destroy'])->middleware('permission:subscriptions.delete')->name('subscriptions.destroy');
        Route::post('{serverSubscription}/renew', [$ctrl, 'renew'])->middleware('permission:subscriptions.renew')->name('subscriptions.renew');
    });
});
