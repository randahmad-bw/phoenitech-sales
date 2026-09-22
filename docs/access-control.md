# Authentication, Roles & Permissions — Reference

> نظام الإدارة العامة (PhoeniTech Management). هذا المرجع يوثّق نظام تسجيل الدخول
> والأدوار والصلاحيات بالكامل. حدّثه مع كل تغيير على هذه الطبقة.
>
> This document is the source of truth for how auth & access control work in this
> project. Keep it updated whenever the auth/RBAC layer changes.

Last updated: 2026-09-22.

---

## 1. Overview

- **Stack:** Laravel 12 API (`/api/v1`) + Sanctum token auth + React SPA (`Frontend/`).
- **Authorization:** `spatie/laravel-permission` **^6.25** (v8 needs PHP 8.3; local dev is PHP 8.2). Guard: `web`.
- **Project scope:** general company management (renamed from "Sales"): employees, contracts, payments, subscriptions, social media, reports — and the upcoming attendance/HR module.

Delivery so far (phases):

| Phase | What | Status |
|-------|------|--------|
| 0 | Security hardening (CORS, login throttle, token expiry, dead-code removal) | ✅ |
| 1 | RBAC foundation (spatie install, tables, roles, permissions, seeders, super_admin) | ✅ |
| 2 | Enforcement (permission middleware on every route, data scoping, active-account guard) | ✅ |
| 3 | Auth hardening (email/username login, is_active at login, last-login, change password) | ✅ |
| 4 | User & Role management API (CRUD, activation, password reset, guards) | ✅ |
| 5 | Audit log | ⏳ pending |
| 6 | Frontend access control (route guards, `<Can>`, admin screens, change-password UI) | ⏳ pending |
| 7 | Attendance module — Phase 1: schema, models, `staff` role, accounts | ✅ |
| 8 | Attendance module — Phase 2: self-service check-in / check-out / history | ✅ |
| 9 | Attendance module — Phase 3: management dashboard, corrections, schedules, nightly close | ✅ |
| 10 | Attendance module — Phase 4: frontend (3 screens, ar/en, RTL) | ✅ |
| 11 | Attendance module — Phase 5: work location, open-ended days, attendance opt-out | ✅ |
| 12 | Dashboard permission, per-role landing, installable app (PWA) | ✅ |
| 13 | 12-hour display, per-employee schedule editing, attendance hidden from those outside it | ✅ |
| 14 | Employees screen restricted to `employees.view_all`; own details moved to Settings | ✅ |
| 15 | Live seconds on the running shift counter | ✅ |
| 16 | Bidi fix: numeric time runs isolated from the RTL layout | ✅ |
| 17 | Multi-session days: returning to work records extra work, approved by management | ✅ |
| 18 | Leave requests: submit / approve split — closes a self-approval escalation | ✅ |
| 19 | UX foundation: sidebar grouping, shared page primitives, global feedback layer | ✅ |
| 20 | Role set restructured: access separated from department; `admin`/`hr` retired | ✅ |

---

## 2. Login accounts (seeded)

Created by `UserSeeder`; roles assigned by `RolePermissionSeeder`. All emails use `@phoenitech.sy`.
Seed password comes from `env('SEED_PASSWORD', 'password')` — **override it in production**.

| Email (login) | Name | Role | Department |
|---|---|---|---|
| `info@phoenitech.sy` | الإدارة | **super_admin** | — |
| `admin@phoenitech.sy` | System Admin | **super_admin** | — |
| `antoine.haddad@phoenitech.sy` | Antoine Haddad | general_manager | management |
| `rand.ahmad@phoenitech.sy` | Rand Ahmad | general_manager | management |
| `sara@phoenitech.sy` | سارة حسون | sales | sales |
| `michael@phoenitech.sy` | مايكل حبيب | sales | sales |
| `hla.shindeah@phoenitech.sy` | حلا شنديعة | **team** | design |
| `ayman.shaaban@phoenitech.sy` | أيمن شعبان | **team** | photography |
| `sabine.barbahan@phoenitech.sy` | سابين بربهان | **team** | design |
| `majed@phoenitech.sy` | ماجد | **team** | design |
| `kamal@` `omar@` `zain@` `marwan@` `nawal@` | كمال عمر زين مروان نوال | **team** | design |

Two accounts hold `super_admin`: the owner, and a technical account kept as a way back in.
Everyone else runs the work without being able to create logins.

Every employee profile is linked to an account (`employees.user_id`): the attendance module
needs a login per person to record a check-in against.

Login accepts **email OR username** in the `email` field. Disabled accounts (`is_active = false`) cannot log in.

`users` table auth columns (added by migration `..._add_auth_fields_to_users_table`):
`username` (unique, nullable), `is_active` (default true), `last_login_at`, `last_login_ip`, `must_change_password`.

---

## 3. Roles

**A role says what an account may reach. It does not say what the person does for a
living** — that is their *department*, on the employee record. Keeping the two apart is
what stops the set from sprawling, and it is the fix described in §32.

Six roles (editable from the dashboard except `super_admin`):

| Role | Who | Reach |
|---|---|---|
| **super_admin** | The owner, plus one technical account | Everything, and the only role that manages accounts and roles |
| **general_manager** | مدير عام — Antoine, Rand | Every operational permission. **No `users.*` or `roles.*`** |
| **sales_manager** | مدير مبيعات | Clients, contracts, payments, reports; their team's leave, overtime, attendance and tasks |
| **sales** | مبيعات | `contracts.view_own`, weekly reports, dashboard, own attendance, leave and tasks |
| **marketing** | تسويق | The social-media module, own attendance, leave and tasks. **No dashboard** |
| **team** | تصميم / تطوير / تصوير / مونتاج | Own profile, own attendance (incl. check-in/out), own leave, own tasks. **Nothing commercial** |

- `super_admin` bypasses every check via `Gate::before` (AppServiceProvider). Protected:
  cannot be renamed or deleted, and the *last* active one cannot be deleted, disabled or demoted.
- `general_manager` is deliberately not `super_admin`: the person who runs the business
  should not also be the person who can quietly widen their own access.
- Four departments share the `team` role because their access is identical. `marketing` is
  a role of its own only because the social-media module genuinely differs.

**Departments** (`Employee::DEPARTMENTS`, validated on create and update):
`sales` · `marketing` · `design` · `dev` · `photography` · `video` · `management`.
Labels live in `employee.department_<id>` in both locales; the frontend mirror is
`Frontend/src/constants/departments.ts`.

## 4. Permission catalog (71 permissions)

Format `group.action`. Groups:

```
users        view create edit delete activate
roles        view create edit delete assign_permissions
employees    view_all view_own create edit delete view_salary
companies    view create edit delete
contracts    view_all view_own create edit delete renew
payments     view create edit delete
subscriptions view create edit delete renew
social_media view create edit delete
leave        view_all view_own create approve reject
overtime     view_all view_own create approve reject
reports      view export
weekly_reports view_all view_own create delete
dashboard    view
settings     view edit
audit        view
attendance   view_all view_own create edit delete approve manage_schedules
tasks        view_all view_own create edit delete
```

`view_all` vs `view_own`: routes allow either; the **service layer** then scopes rows via `App\Application\Support\AccessScope` (a user without `*.view_all` only sees their own linked employee's records).

---

## 5. How enforcement works

1. **Route middleware** (`routes/api.php`): every protected route declares `permission:<name>` (pipe = OR, e.g. `employees.view_all|employees.view_own`). The whole group is behind `auth:sanctum` + `active`.
   - `App\Http\Middleware\CheckPermission` → 403 `FORBIDDEN` if the user holds none of the listed permissions.
   - `App\Http\Middleware\EnsureUserIsActive` → 403 if `is_active = false`.
2. **super_admin** passes everything via `Gate::before` in `AppServiceProvider::boot()`.
3. **Data scoping** (`AccessScope`): `ownEmployeeId()` restricts list queries; `canAccessEmployee()` guards nested `/employees/{employee}/…` endpoints.
4. **Business rules** live in the services and throw `App\Exceptions\BusinessRuleException` → rendered as 409 (bootstrap/app.php).

> **Frontend is presentation only.** Hiding buttons/pages is UX; the backend rejects unauthorized requests regardless. Verified by `tests/Feature/AuthorizationTest.php`.

### Guard pitfall (important)
`auth:sanctum` rewrites `config('auth.defaults.guard')` to `sanctum` during a request, but permissions are stored under `web`. Never read `config('auth.defaults.guard')` to resolve permission/role guards at request time — use `Spatie\Permission\Guard::getDefaultName(User::class)` (as `RoleService` does).

---

## 6. Endpoints

### Auth (`/api/v1/auth`)
| Method | Path | Permission | Notes |
|---|---|---|---|
| POST | `auth/login` | public | email/username + password; throttled `login`; checks is_active; records last_login |
| POST | `auth/logout` | authenticated | revokes current token |
| GET | `auth/me` | authenticated | returns user incl. `roles`, `permissions`, `is_active` |
| POST | `auth/change-password` | authenticated + active | current + new (min 8, letters+numbers); revokes other tokens |

### Users (`/api/v1/users`)
| Method | Path | Permission |
|---|---|---|
| GET | `users` | users.view |
| POST | `users` | users.create |
| GET | `users/{user}` | users.view |
| PUT/PATCH | `users/{user}` | users.edit |
| DELETE | `users/{user}` | users.delete |
| PATCH | `users/{user}/status` | users.activate |
| POST | `users/{user}/reset-password` | users.edit |

Guards: no self-delete, no self-disable, cannot delete/disable/demote the last super_admin. Disabling or resetting a password revokes that user's tokens.

### Roles & Permissions
| Method | Path | Permission |
|---|---|---|
| GET | `permissions` | roles.view (grouped catalog for the editor) |
| GET | `roles` | roles.view |
| POST | `roles` | roles.create |
| GET | `roles/{role}` | roles.view |
| PUT/PATCH | `roles/{role}` | roles.edit |
| DELETE | `roles/{role}` | roles.delete |

Guards: `super_admin` role cannot be renamed/deleted; a role still assigned to users cannot be deleted (409 `ROLE_IN_USE`).

### Audit trail (`/api/v1/audit-logs`) — read only
| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `audit-logs` | audit.view | filters: `user_id`, `event`, `type`, `record_id`, `date_from`, `date_to`, `search`, `per_page` (max 100) |
| GET | `audit-logs/filters` | audit.view | events / record types / actors present in the trail, for the filter dropdowns |
| GET | `audit-logs/record/{type}/{id}` | audit.view | full history of one record, e.g. `record/contract/42` |
| GET | `audit-logs/{auditLog}` | audit.view | one entry with its full diff |

There is no POST/PUT/DELETE: the trail is append-only. See §12.

All responses use the standard envelope (`App\Http\Responses\ApiResponse`): `{ success, message, data?, error_code?, errors?, meta? }`.

---

## 7. Security hardening (Phase 0)
- CORS: no wildcard origin with credentials; real origins via `CORS_ALLOWED_ORIGINS`. The old catch-all `OPTIONS` route (which reflected any Origin) was removed.
- Login rate limiting: `login` limiter — 5/min per email+IP and 20/min per IP; general `api` limiter 120/min. Defined in `AppServiceProvider`.
- Sanctum token expiry: `SANCTUM_EXPIRATION` (default 7 days).
- Passwords always bcrypt (hashed cast); distinct per user.

---

## 8. Running it

```bash
composer install
php artisan migrate                 # includes spatie tables + user auth columns
php artisan db:seed                 # UserSeeder → RolePermissionSeeder → domain seeders
# or just the access layer:
php artisan db:seed --class=RolePermissionSeeder
```

Production checklist: set `SEED_PASSWORD`, `CORS_ALLOWED_ORIGINS`, `APP_DEBUG=false`.

`RolePermissionSeeder` flushes the spatie permission cache **twice**: once before it reads
anything, and once after the catalog is created. Both are required.

- Before: the cache store (`CACHE_STORE=database`, 24h TTL) survives a `migrate:fresh`, and a
  stale map makes `findOrCreate()` insert a permission that already exists → unique-index error.
- After the catalog loop: `DatabaseSeeder` uses `WithoutModelEvents`, so creating a `Permission`
  never fires the `saved` hook that normally refreshes the cache. Without the second flush the
  registrar still holds the empty map it loaded on a fresh database and every `syncPermissions()`
  throws `PermissionDoesNotExist`.

Same rule applies to any new seeder that creates permissions or roles. If a permission change
still looks like it did not apply, run `php artisan permission:cache-reset`.

Local note: dev is PHP 8.2 (XAMPP); production DB is MariaDB 11.4 / PHP 8.4. Tests run on in-memory SQLite (`php artisan test`).

---

## 9. Key files

```
app/Http/Middleware/CheckPermission.php        route permission guard (pipe = OR)
app/Http/Middleware/EnsureUserIsActive.php     blocks disabled accounts
app/Application/Support/AccessScope.php        view_own vs view_all scoping
app/Application/Services/RoleService.php        role CRUD + permission sync (+ guard fix)
app/Application/Services/UserManagementService.php  user CRUD + safety guards
app/Http/Controllers/Api/V1/AuthController.php  login/logout/me/change-password
app/Http/Controllers/Api/V1/UserController.php  admin user management
app/Http/Controllers/Api/V1/RoleController.php  role management
app/Exceptions/BusinessRuleException.php        → 409 Conflict
database/seeders/{UserSeeder,RolePermissionSeeder,EmployeeSeeder}.php
config/permission.php                           spatie config
routes/api.php                                  all routes + their permissions
tests/Feature/{Authorization,AuthHardening,UserManagement,RoleManagement,SecurityHardening}Test.php

— audit trail —
app/Models/AuditLog.php                         the entry + EVENT_* constants + type aliases
app/Models/Concerns/Auditable.php               trait: created/updated/deleted → trail
app/Application/Support/AuditLogger.php         the only writer (actor, context, sanitising)
app/Application/Services/AuditLogService.php    filtering, options, prune
app/Http/Controllers/Api/V1/AuditLogController.php
app/Http/Resources/AuditLogResource.php         adds the pre-computed `changes` diff
app/Console/Commands/PruneAuditLogs.php         `audit:prune --days=`
config/audit.php                                switch, retention, excluded attributes
tests/Feature/AuditLogTest.php
```

---

## 10. Decisions log
- **Super admin = `info@phoenitech.sy`** (the management account), not rand.ahmad. Rand is Operations Manager (`manager`).
- **`abdullah@` consolidated into `info@`** (same management person). Seeders don't delete existing prod rows.
- **Password reset by email is deferred** (MAIL=log, internal tool). Admins reset passwords via `users/{user}/reset-password` (sets `must_change_password=true`).
- **spatie v6, not v8** — PHP 8.2 constraint.
- **`WeeklyReportController`** old admin-detection heuristic was replaced by the permission layer (it conflicted with super_admin having an employee record).
- **Audit trail is append-only with snapshotted actor/label**, so an entry stays readable after the user or the record is deleted. Only `audit:prune` removes rows.

## 11. Pending / open
- ~~Dashboard (`/dashboard`) is open to any authenticated user and exposes company financials.~~ Resolved — now behind `dashboard.view`; see §19.
- ~~Give login accounts to profile-only employees (design/photography) before the attendance module.~~ Done — §2 and §14.
- The frontend lives outside this repository (`.gitignore` excludes `Frontend/`); only its build is deployed.

---

## 12. Audit trail (Phase 5)

**What it answers:** who did what, when, and what changed.

### The entry
`audit_logs` is append-only — no `updated_at`, and nothing in the app edits a row. Columns:

| Group | Columns |
|---|---|
| Actor | `user_id` (null on delete) + `user_name` / `user_email` **snapshots**, so the trail still names who acted after the account is gone |
| Event | `event` — see `AuditLog::EVENT_*` |
| Target | `auditable_type` (FQCN) + `auditable_id` + `auditable_label` (snapshot, e.g. the contract number) |
| Change | `old_values` / `new_values` (JSON, only the fields that actually changed) |
| Context | `ip_address`, `user_agent`, `url`, `method`, `created_at` |

Events: `created`, `updated`, `deleted`, `login`, `login_failed`, `login_blocked`, `logout`, `password_changed`, `password_reset`, `roles_changed`, `permissions_changed`.

### How entries are written
- **Model changes** — `use App\Models\Concerns\Auditable;` on a model logs create/update/delete automatically. An update with an empty diff writes nothing. Per-model noise is silenced with `protected array $auditExclude = [...]` (e.g. `User` excludes `last_login_at`), and `protected string $auditLabelAttribute` picks the label column.
  Currently audited: User, Employee, Company, Contact, Contract, Payment, Service, Attachment, WeeklyReport, EmployeeLeave, EmployeeOvertime, ServerSubscription, and the four Social-Media models.
- **Auth events** — written in `AuthController` (login, failed login with the attempted identifier, login blocked for a disabled account, logout, password change).
- **Pivot-only changes** — spatie's `syncRoles`/`syncPermissions` fire no model events, so `UserManagementService` and `RoleService` log `roles_changed` / `permissions_changed` explicitly with the before/after lists.

Everything goes through `AuditLogger::record()`, which resolves the actor, captures request context, and **never throws** — a trail failure is logged and swallowed so it cannot break the action it describes.

### What is never stored
`password`, `remember_token`, two-factor secrets and the timestamp columns are stripped from every entry (`config/audit.php → excluded_attributes`). A password change is recorded as a *fact*, never as a value. Long strings are capped at `max_value_length` (2000 chars).

### Deliberate blind spots
Writes that bypass Eloquent events are **not** recorded: `Model::query()->update(...)`, raw SQL, and seeders running under `WithoutModelEvents`. This is intentional — the trail describes user actions, not bulk maintenance. `contracts:expire` (a mass `update()`) is therefore invisible to it.

### Switches & retention
```bash
AUDIT_ENABLED=true          # master switch (config/audit.php)
AUDIT_RETENTION_DAYS=365    # 0 = keep forever

php artisan audit:prune --days=365   # scheduled weekly, Mon 01:30
```
In code, `AuditLogger::withoutAuditing(fn () => ...)` silences a block (used by imports and tests) and restores the previous state even if the callback throws.
```

---

## 13. Frontend access control (Phase 6)

The UI mirrors the backend rules so people are not shown doors they cannot open.
**None of it is a security boundary** — every gate below is cosmetic, and the
`permission:` middleware on the API route remains the only thing that actually
protects data.

### Where permissions come from
`GET /auth/me` returns `roles` and the flattened `permissions` list
(`UserResource`). `authStore` keeps them on `user` and exposes `can`, `canAll`
and `hasRole`; `super_admin` short-circuits to `true`, mirroring `Gate::before`.

`isInitialized` guards the whole thing: it stays `false` until the first
`/auth/me` settles. Without it a page refresh would evaluate permissions against
an empty profile and bounce a legitimate user to `/403`.

### The pieces
| Piece | File | What it does |
|---|---|---|
| Store helpers | `store/authStore.ts` | `can` / `canAll` / `hasRole`, `changePassword`, `isInitialized` |
| Reactive hook | `hooks/usePermissions.ts` | subscribes to `user` — use this inside components |
| `<Can do="...">` | `components/auth/Can.tsx` | renders children only if permitted; `do` accepts an array (any-of), `all` switches to every-of |
| Route guard | `components/auth/RequirePermission.tsx` | layout route → `/403` when denied, spinner until the profile loads |
| 403 page | `pages/ForbiddenPage.tsx` | deliberately vague about what sits behind the wall |
| Sidebar | `components/layout/Sidebar.tsx` | each entry carries the matching permission; the admin group is its own section |
| Forced change | `pages/ForcePasswordChangePage.tsx` | full-screen gate while `must_change_password` is set — only sign-out escapes it |

### Screens
- **`/users`** (`users.view`) — list, search, filter by role and status; create,
  edit, activate/disable, reset password, delete. Self-deactivation and
  self-deletion are disabled in the UI *and* refused by the backend (409); the
  server's message is surfaced verbatim rather than re-deriving the rule.
- **`/roles`** (`roles.view`) — role list with user counts plus the permission
  matrix, grouped with select-all per group. `super_admin` is read-only: it
  bypasses every check anyway, so editing it would be theatre.
- **`/audit-logs`** (`audit.view`) — filter by event, record type, actor, date
  range and free text; a row opens the field-by-field diff with the request
  context. Read-only, like the API.
- **Settings** — roles of the signed-in account and the change-password form.

### i18n
Every string is keyed in `i18n/ar.json` and `i18n/en.json`:
`access.*`, `roles.*`, `permission_groups.*`, `permissions.<group>.<action>`,
`audit_events.*`, `audit_types.*`. Permission names carry a dot, so they are
authored as nested objects — i18next resolves `permissions.users.view` through
its key separator.

---

## 14. Attendance module — Phase 1 (schema & access)

**Status:** foundation only. No endpoints yet — check-in/check-out, the dashboards and
schedule management land in Phase 2. What exists today is the schema, the models and the
access layer they sit behind.

### The `staff` role (new)

Design, photography and video staff were profile-only until now. The attendance module needs
a login per person, so they were given accounts — but **not** the `employee` role, which
carries `contracts.view_own` and the weekly-report workflow. Contracts belong to sales, the
sales manager and management.

```php
'staff' => [
    'employees.view_own',
    'attendance.view_own', 'attendance.create',
    'leave.view_own', 'leave.create',
    'overtime.view_own',
],
```

`employee` keeps its meaning and its contract access: it is the **sales** role.
Enforced and proven by `tests/Feature/AttendanceFoundationTest.php`.

> **Renamed in phase 20.** `staff` is now `team` and `employee` is now `sales`; the
> permission sets are unchanged. The naming above is exactly what §32 set out to fix.

### `attendance.manage_schedules` (new permission)

The 65th permission. Kept apart from `attendance.edit` because correcting one day's record is
routine while redefining someone's working week is not. **Admin only** — not granted to
`manager`, `hr`, `employee` or `staff`.

| Permission | Meaning |
|---|---|
| `attendance.view_own` | own record and today's card |
| `attendance.create` | **own check-in / check-out** — no separate check_in/check_out permission, since nobody is granted one without the other |
| `attendance.view_all` | management dashboard, any employee's history |
| `attendance.edit` | corrections (a reason is required) |
| `attendance.delete` | remove a record |
| `attendance.approve` | reserved for Phase 2+ |
| `attendance.manage_schedules` | schedules and holidays |

### Tables

| Table | Holds |
|---|---|
| `work_schedules` | a schedule template, e.g. "Office 09:00–17:00" |
| `work_schedule_days` | one row per working weekday — **a weekday with no row is not a working day** |
| `employee_schedules` | date-ranged assignment of a template to an employee |
| `attendances` | the daily record, one row per employee per day (unique) |
| `holidays` | company and public holidays |

### Design decisions worth knowing

**No global working week.** One employee starts Saturday, another Sunday, a third works
Monday/Tuesday/Wednesday/Saturday. This is expressed entirely by which `work_schedule_days`
rows exist. `config('attendance.week_start')` is display order only and governs nothing.
`weekday` uses Carbon numbering (0 = Sunday … 6 = Saturday).

**Schedules are never edited in place.** Changing someone's schedule closes the running
`employee_schedules` row (`effective_to`) and opens a new one, so "which schedule was this
person on last March?" stays answerable and a temporary change is just a row with both ends
set.

**Attendance rows snapshot the schedule.** `scheduled_start`, `scheduled_end`,
`expected_minutes` and `break_minutes` are copied onto the row. Editing or deleting a template
can never rewrite what a past day was expected to be — the same snapshotting principle the
audit trail uses for actor names (§12).

**Facts, not judgments.** There is no lateness, early-leave, grace period or overtime column.
The system records when you checked in, when you checked out, and the hours between. Because
both the actual times *and* the schedule-as-it-was live on every row, any of those rules can
be introduced later and computed retroactively over historical data — which is exactly why
the snapshot columns are kept.

**Statuses:** `present` · `absent` · `incomplete` · `day_off` · `holiday` · `leave`.
"Currently working" is derived (`check_in_at` set, `check_out_at` null, date = today), not
stored — one flag cannot mean both "still working" and "forgot to check out".
Only `present` / `incomplete` / `absent` get rows; day off, holiday and leave are derived on
read from the schedule, `holidays` and approved `employee_leaves`.

**A day off earns no overtime** (management decision). Hours worked on a day off are recorded
with `expected_minutes = 0` and credit nothing.

**Double check-in is impossible at the database level:** `unique(employee_id, work_date)`.
That, not application logic, is what defeats duplicate and concurrent requests.

**Corrections need no new table.** `Attendance` uses the `Auditable` trait, so every change
lands in `audit_logs` with actor, timestamp and a before/after diff. `correction_reason` is
required on the correction endpoint (Phase 2) and appears in that diff. `source`
(`self` / `manual` / `system`) records who produced the row.

### Timezone
`config/app.php` stays `UTC` and the two datetimes are ordinary UTC timestamps, but
`work_date` and every comparison resolve through `config('attendance.timezone')`
(`Asia/Damascus`). Otherwise a 22:00 Damascus check-in would be filed under the next day.

### Key files
```
config/attendance.php                                timezone, week display, day-off policy
app/Models/{Attendance,WorkSchedule,WorkScheduleDay,EmployeeSchedule,Holiday}.php
database/migrations/2026_09_19_1300*                 the five tables
database/seeders/RolePermissionSeeder.php            staff role + manage_schedules
database/seeders/{User,Employee}Seeder.php           the four new accounts and their links
tests/Feature/AttendanceFoundationTest.php
AiMind/attendance_plan.md                            full architecture proposal (V1/V2 scope)
```

---

## 15. Attendance module — Phase 2 (self-service)

The four endpoints behind the employee's screen. Management endpoints
(overview, corrections, schedule management) are Phase 3.

| Method | Path | Permission |
|---|---|---|
| GET | `attendance/today` | `attendance.view_own\|attendance.view_all` |
| GET | `attendance/my` | `attendance.view_own\|attendance.view_all` |
| POST | `attendance/check-in` | `attendance.create` |
| POST | `attendance/check-out` | `attendance.create` |

### Why these endpoints cannot be gamed

`check-in` and `check-out` accept **only an optional `notes` string**. No employee id, no
time, no date, no status — `PunchAttendanceRequest` allows nothing else, the employee is
read from the Sanctum token, and the clock comes from the server. There is no parameter to
tamper with rather than a validation rule that says "don't". Proven by
`a_client_supplied_time_employee_or_status_is_ignored`, which posts all four and asserts none
of them lands.

### `attendance/today` — the whole screen in one call

```json
{ "state": "not_checked_in|working|completed|day_off|holiday|leave",
  "work_date": "2026-09-16", "server_time": "2026-09-16T09:00:00+03:00",
  "has_schedule": true, "is_working_day": true,
  "schedule": { "name": "…", "start": "09:00", "end": "17:00",
                "expected_minutes": 420, "break_minutes": 60 },
  "attendance": { … } | null,
  "can_check_in": true, "can_check_out": false }
```

`can_check_in` / `can_check_out` are **decided on the server**. The button renders from those
booleans, so the browser never re-implements the rules and cannot drift from them.
`server_time` lets a live "working for 3:12" counter measure against the server's clock
rather than the device's.

Check-in and check-out return this same payload for **the day they touched** — not for the
current calendar day. A night shift closed at 02:30 belongs to the day it started, and the
employee sees the shift they just finished rather than tomorrow's empty card.

### Business rules (409 `BusinessRuleException` → error codes)

| Code | When |
|---|---|
| `ALREADY_CHECKED_IN` | a second check-in the same day |
| `NOT_CHECKED_IN` | check-out with nothing open |
| `ALREADY_CHECKED_OUT` | check-out after the day is closed |
| `STALE_OPEN_ATTENDANCE` | an open row older than yesterday — closing it now would record an impossible shift, so management must correct it |
| `NO_SCHEDULE_ASSIGNED` | the employee has no schedule for this date |
| `NOT_A_WORKING_DAY` | only when `attendance.allow_check_in_on_day_off` is off |
| `NO_EMPLOYEE_PROFILE` | the account has no linked employee row |

### Concurrency

Every write runs inside `DB::transaction` and takes `lockForUpdate()` on the day's row before
deciding, with `unique(employee_id, work_date)` underneath. Two taps on a slow connection
produce one row and one 409 — the database is the guarantee, not the application logic.

### Overnight shifts
Check-out finds the row by **open-ness**, not by today's date
(`whereNotNull(check_in_at)->whereNull(check_out_at)`), so a shift crossing midnight is not
orphaned at 00:00. It is bounded to yesterday-or-later, which is what `STALE_OPEN_ATTENDANCE`
enforces.

### Key files
```
app/Application/Support/ScheduleResolver.php     schedule / holiday / leave for a date + the server clock
app/Application/Support/ScheduledDay.php         value object: the schedule and day in force
app/Application/Support/TodayAttendance.php      the screen state, incl. the two button booleans
app/Application/Services/AttendanceService.php   check in / out, history, summary
app/Http/Controllers/Api/V1/AttendanceController.php
app/Http/Requests/PunchAttendanceRequest.php     deliberately allows only `notes`
app/Http/Requests/AttendanceHistoryRequest.php   period / from-to / month, resolved in company tz
app/Http/Resources/{Attendance,AttendanceTodayResource}.php
tests/Feature/AttendanceCheckInTest.php
```

---

## 16. Attendance module — Phase 3 (management)

### Endpoints

| Method | Path | Permission |
|---|---|---|
| GET | `attendance/overview` | `attendance.view_all` |
| GET | `attendance` | `attendance.view_all` |
| POST | `attendance` | `attendance.edit` |
| PUT/PATCH | `attendance/{attendance}` | `attendance.edit` |
| DELETE | `attendance/{attendance}` | `attendance.delete` |
| GET | `employees/{employee}/attendance` | `attendance.view_all\|attendance.view_own` |
| GET/POST/PUT/DELETE | `work-schedules[/{workSchedule}]` | `attendance.manage_schedules` |
| GET/POST | `employees/{employee}/schedules` | `attendance.manage_schedules` |
| GET/POST/PUT/DELETE | `holidays[/{holiday}]` | `attendance.manage_schedules` |

Filters on the grid: `date`, `employee_id`, `department`, `status`.
`employees/{employee}/attendance` accepts `month=YYYY-MM` or `from`/`to`/`period`.

### Two controllers, on purpose
`AttendanceController` (self-service) accepts nothing but an optional note.
`AttendanceManagementController` accepts employees, times and statuses. Splitting them
means widening one cannot accidentally widen the other, and the permissions differ:
an employee may record their own day but **may not edit it** — proven by
`staff_cannot_see_the_company_dashboard_or_correct_anything`.

### The reader fills gaps; the writer does not
Days off, holidays and approved leave are **never stored**. `dayGrid()` and
`employeeGrid()` build unsaved `Attendance` instances for them, so every screen sees a
complete calendar while the table stays proportional to actual work. A derived row has
`id === null`.

A seventh status exists for this: **`pending`** — a working day that has not happened yet
(today, before the check-in). It is derived only, deliberately absent from
`Attendance::STATUSES`, and therefore impossible to store through a manual entry or a
correction. Without it the grid would have to call today either an absence or a presence,
and both would be untrue.

`overview` also returns **`without_schedule`** — employees with no schedule assigned. Not a
status, but the thing management most needs to act on: nobody can record attendance until
they have one.

### Corrections
`reason` is **required** (422 otherwise). The service recomputes `worked_minutes`, sets
`source = manual` and `updated_by`, and the `Auditable` trait writes the before/after diff to
`audit_logs` — actor, IP, URL, and the previous value. Nothing is silently overwritten.

Correcting a non-working day keeps its status: adding hours to a day off does not turn it
into a working day, and it still earns no overtime.

`AuditLog::TYPE_ALIASES` gained `attendance`, `work_schedule`, `employee_schedule` and
`holiday`, so `GET audit-logs/record/attendance/{id}` returns a record's full history.

### Schedule assignment is append-only
`POST employees/{employee}/schedules` closes the running assignment
(`effective_to` = the day before) and inserts a new row. A start date on or before the
current assignment's start is refused with 409 `SCHEDULE_OVERLAP`, so the ranges never
overlap and every past date resolves to exactly one schedule. A schedule still assigned to
someone cannot be deleted (409 `WORK_SCHEDULE_IN_USE`) — deactivate it instead.

Editing a template's days does not touch history: every attendance row carries its own
snapshot of the times that applied on that date.

### Timezone trap (worth knowing before touching this code)
Eloquent stores a datetime by formatting its **wall-clock digits**. Handing it a `+03:00`
instant writes "09:00" and reads it back as 09:00 **UTC** — noon in Damascus. Anywhere a
company-timezone time is built for storage it must be converted with `->utc()`, not merely
constructed in the right zone. This applies to
`AttendanceManagementService::combine()` and `CloseAttendanceDay::closeOpenRow()`.
Self-service check-in/out is unaffected because it uses `now()`, which is already UTC.

### `attendance:close-day`
```bash
php artisan attendance:close-day                  # yesterday
php artisan attendance:close-day --date=2026-09-15
php artisan attendance:close-day --dry-run
```
Scheduled daily at **02:00 in the company timezone** (`routes/console.php`).

For the target day, per employee holding a login account:
1. an open row (checked in, never out) becomes `incomplete` — **no departure time is
   invented**; only a human knows when the person left, so management corrects it with a
   reason. `attendance.auto_checkout_on_close` can change this and is off by default.
2. a scheduled working day with no row becomes `absent`, `source = system`, with the
   schedule snapshotted.
3. days off, holidays and approved leave are skipped entirely — they produce no rows.

It refuses to close a day that is not over.

### Key files
```
app/Application/Services/AttendanceManagementService.php   grids, overview, manual entry, corrections
app/Application/Services/WorkScheduleService.php           templates + append-only assignment
app/Application/Services/HolidayService.php
app/Console/Commands/CloseAttendanceDay.php
app/Http/Controllers/Api/V1/{AttendanceManagement,WorkSchedule,Holiday}Controller.php
app/Http/Requests/{StoreAttendance,UpdateAttendance,AttendanceFilter}Request.php
app/Http/Requests/{StoreWorkSchedule,UpdateWorkSchedule,AssignWorkSchedule,StoreHoliday}Request.php
app/Http/Resources/{WorkSchedule,EmployeeSchedule,Holiday}Resource.php
tests/Feature/{AttendanceManagement,AttendanceSchedule}Test.php
```

---

## 17. Attendance module — Phase 4 (frontend)

Three screens in `Frontend/`, each gated by `RequirePermission` mirroring the API route.

| Route | Permission | Who sees it |
|---|---|---|
| `/attendance` | `attendance.view_own\|attendance.view_all` | every employee — their own card and history |
| `/attendance/manage` | `attendance.view_all` | management dashboard for one day |
| `/leaves` | `leave.view_own\|leave.view_all` | requesting leave, and deciding on it — see §33 |
| `/attendance/schedules` | `attendance.manage_schedules` | admin — templates, assignment, holidays |

All four sit in the **الدوام** sidebar group and share one frame
(`components/attendance/AttendanceShell.tsx`), which draws the strip of links between them
from permissions — never from a role name — and hides it when only one view is reachable.

### The employee screen renders, it does not decide
`GET attendance/today` returns `can_check_in` / `can_check_out`, and the page renders exactly
those two booleans. No permission check, no clock comparison and no status logic is
re-implemented in the browser, so the UI cannot drift out of step with the API. On a 409 the
server's message is shown **verbatim** rather than re-derived — it already knows which rule
was hit.

The running counter for an open shift measures elapsed time from `server_time` plus the
seconds the tab has been open, never from `Date.now()` directly: a device with a wrong clock
would otherwise show a wrong figure.

### Management screen
Counters, then a filterable table (date / department / status). Derived rows (day off,
holiday, leave, `pending`) arrive with `id === null`, and the correct button is hidden for
them — there is no record yet to correct. The correction modal requires a reason and says
why, and surfaces `without_schedule` as a banner because that is the thing that actually
blocks people from recording anything.

### Schedule editor
A checkbox per weekday, ordered Saturday-first (`config('attendance.week_start')` on the
server, `WEEK_ORDER` in `components/attendance/attendanceUi.ts` on the client). A day left
unchecked is simply omitted from the payload — that is how "does not work Fridays" is
expressed, and why two employees can have different week starts.

### i18n
97 keys under `attendance.*` in **both** `ar.json` and `en.json`, plus `nav.attendance*`.
Statuses are keyed `attendance.status_<status>` and states `attendance.state_<state>`, so
both come straight from the API value with no mapping table.

### Files
```
Frontend/src/types/attendance.ts
Frontend/src/api/attendance.ts                       attendanceApi / workScheduleApi / holidayApi
Frontend/src/hooks/queries.ts                        useAttendanceToday, useAttendancePunch, …
Frontend/src/components/attendance/attendanceUi.ts   status colours, duration and weekday helpers
Frontend/src/pages/AttendancePage.tsx                employee card + history
Frontend/src/pages/AttendanceManagePage.tsx          dashboard + correction + monthly modal
Frontend/src/pages/AttendanceSchedulesPage.tsx       templates, assignment, holidays
```

---

## 18. Attendance module — Phase 5 (real schedules: location, open-ended days, opt-out)

Three facts the first design did not carry, added 2026-09-20 from management's actual
schedules.

### 1. Work location, per **day**
`work_schedule_days.location` — `office` | `remote`, default `office`.

It is on the day, not the employee, because that is how people actually work: one person is
in the office Saturday and Sunday and at home Monday to Wednesday; another is online Monday
to Thursday and in the office on Sunday. Putting it on the employee would make those
unrepresentable.

Snapshotted onto `attendances.location` at check-in, so moving someone to the office next
month does not rewrite where they worked last month. **Null on a day off** rather than
defaulted — nothing was expected, so there is no place it was expected at.

### 2. Open-ended days
`work_schedule_days.end_time` is now **nullable**, meaning: a fixed start, and the employee
leaves when the work is done.

`expected_minutes` is then `0` — there is no figure to measure the day against. That costs
nothing here, because the module records hours and does not grade them (§14). The hours
actually worked are still recorded in full.

The UI shows "مفتوح / Open" rather than a dash: the start is known, only the end is
deliberately unset.

### 3. Employees outside the module
`employees.tracks_attendance` (default `true`).

Management is outside the attendance system by decision, and sales work by field visits with
no fixed hours or place — a check-in would measure nothing. Marked `false`, they are:
- excluded from the management grid and its counters,
- skipped by `attendance:close-day` (never marked absent),
- refused at check-in with 409 `ATTENDANCE_NOT_TRACKED`, and `can_check_in` is false.

Kept distinct from "has no schedule yet": without the flag both look identical and the
dashboard would keep prompting to configure people who are never meant to be configured.

### The seeder
`database/seeders/WorkScheduleSeeder.php` holds the real working weeks. Re-runnable:
schedules are found-or-created by name, days are rebuilt, and an employee already on the
right schedule is left alone (no stacked assignments).

| Employee | Days | Hours | Location |
|---|---|---|---|
| كمال · مروان | Sat–Wed | 08:00–13:00 | office |
| عمر · حلا | Sun–Thu | 08:00–13:00 | office |
| زين | Sat–Wed | 08:00–13:00 | office Sat+Sun, **remote** Mon–Wed |
| سابين | Sun–Thu | 08:00–13:00 | office Sun, **remote** Mon–Thu |
| نوال | Sun–Thu | Sun 10:00–**open**, Mon–Thu 08:00–13:00 | office Sun, **remote** Mon–Thu |

Sales and management are set `tracks_attendance = false` by the same seeder.

> Assumption on record: نوال's Monday–Thursday online hours were not stated and are seeded as
> 08:00–13:00, matching the rest of the team. Her Sunday is open-ended as described.

### Testing note (bit me once)
Weekdays in test dates must be checked, not assumed. 2026-09-19 is a **Saturday**,
2026-09-20 a **Sunday**. A schedule whose weekday does not match the frozen test date silently
becomes a day off, and the failure then looks like a location or open-ended bug rather than a
wrong date.

### Files
```
database/migrations/2026_09_20_1000{01,02,03}_*      location, nullable end_time, tracks_attendance
database/seeders/WorkScheduleSeeder.php              the real working weeks
app/Models/WorkScheduleDay.php                       LOCATION_*, isOpenEnded(), isRemote()
app/Models/Employee.php                              scopeTracksAttendance()
app/Application/Support/ScheduledDay.php             location(), isOpenEnded()
tests/Feature/AttendanceLocationTest.php
Frontend/src/components/attendance/attendanceUi.ts   LOCATION_STYLES, locationKey, formatSpan
```

---

## 19. Dashboard permission, per-role landing, and the installable app

### `dashboard.view` — the 66th permission

The landing dashboard aggregates company financials (contract values, payments, revenue).
It was previously open to **any** authenticated account — the open question left in §11.

Resolved: only sales and management see it.

| Role | `dashboard.view` |
|---|---|
| super_admin · admin · manager · hr | ✅ |
| **employee** (sales) | ✅ |
| **staff** (design / photography / video) | ❌ |

`GET /api/v1/dashboard` now carries `permission:dashboard.view`, so this is enforced at the
API, not just hidden in the sidebar. Proven by `tests/Feature/DashboardAccessTest.php`.

### `/` resolves per account
`components/auth/HomeRedirect.tsx` renders the dashboard for anyone holding
`dashboard.view`, and redirects everyone else to `/attendance`.

Deciding it at the route rather than in the login form covers every way in — signing in,
refreshing, a bookmark, the catch-all `*` redirect — with one rule, and avoids the ugly
path of landing on a page that immediately bounces to `/403`. It waits for `isInitialized`,
exactly as `RequirePermission` does, so a refresh does not redirect on an empty permission
list before `/auth/me` returns.

The result for an attendance-only employee: open the app → the check-in button. Nothing else.

### Naming
"حضوري" was replaced with **"الحضور والانصراف"** — the standard professional Arabic term for
clock-in/clock-out — across the nav, the page heading and the manifest shortcut. The
management screen is "متابعة الحضور". English stays "Attendance" / "Attendance Overview".

### Installable app (PWA)

Hand-rolled rather than via `vite-plugin-pwa`: three static files and ten lines of
registration, against a new build dependency and a lockfile change in a repository that is
already in an awkward state (see §20). Nothing here needs a plugin.

| File | Role |
|---|---|
| `public/manifest.webmanifest` | name, icons, `display: standalone`, theme, and a shortcut straight to `/attendance` |
| `public/sw.js` | precaches the app shell so the icon opens instantly; **never touches `/api`** |
| `index.html` | manifest link, `theme-color`, and the three `apple-mobile-web-app-*` tags iOS needs |
| `src/main.tsx` | registers the worker after `load`, failures swallowed |
| `src/components/pwa/InstallPrompt.tsx` | the invitation |

**Attendance is deliberately never cached.** A check-in that "succeeded" from a cache would
be a lie: the server owns the clock and the decision. The worker skips `/api` entirely and
only falls back to the cached shell for navigations, so the app can open offline and show
its own error rather than the browser's.

**The install prompt handles two platforms differently because they are different:**
- **Chromium** fires `beforeinstallprompt`; it is captured, the browser's own mini-infobar
  suppressed, and the invitation replayed behind our button in Arabic.
- **iOS Safari has no install API at all.** The only route is Share → "Add to Home Screen",
  so on iOS the component shows those two steps rather than a button that could not work.

It never appears once the app is installed (`display-mode: standalone`, or
`navigator.standalone` on iOS), and a dismissal is remembered for 14 days —
wrapped in try/catch, since storage can throw in private mode.

> **Requires HTTPS.** Browsers only register a service worker on HTTPS or localhost, so the
> install prompt will not appear over plain HTTP in production.

---

## 20. Per-employee schedules, 12-hour display, and hiding attendance

### Schedules are edited per **employee**, not per template

The template-and-assignment screen was replaced. Management now opens a person and edits
their days, hours and work location directly.

| Method | Path | Permission |
|---|---|---|
| GET | `employee-weeks` | `attendance.manage_schedules` |
| PUT/PATCH | `employees/{employee}/week` | `attendance.manage_schedules` |

**The data model did not change.** `work_schedules` / `work_schedule_days` /
`employee_schedules` still carry the day rows and the dated assignment — they are what keeps
history straight — but nothing exposes them to the user any more.

`WorkScheduleService::setEmployeeWeek()` takes one of two paths:

- the employee's schedule is used by **nobody else** → edit it in place, which is what
  "change this person's hours" should mean;
- the schedule is **shared** (the seeder deliberately shares one week between people who
  work the same days) or there is none → mint a personal schedule for this employee and
  assign it, leaving every colleague's week untouched.

That second rule is the important one, and it is tested: editing Alice must not move Bob.

A same-day second edit repoints the running assignment rather than closing it, which would
otherwise leave a range ending before it began.

`days` is the whole week — a weekday absent from the array is not a working day, and an
empty array is valid (someone on the system with no week set yet). `tracks_attendance` can
be toggled from the same request.

Editing a week never rewrites history: every attendance row carries its own snapshot of the
times, location and expected minutes that applied on that date.

> One accepted limitation: for a **past** date with no stored row, the reader derives
> day-off / holiday from the schedule *as it is now*. The nightly close materialises
> absences daily, so this only affects days that were never closed. Stored rows are always
> correct.

### Attendance is hidden from people it does not apply to

`EmployeeResource` now exposes `tracks_attendance`, so `GET /auth/me` carries it. The
sidebar hides `/attendance` unless `user.employee.tracks_attendance === true`.

Management asked for this specifically: the management account should not be shown a
check-in screen at all. Combined with §19 (`dashboard.view`), the split is now:

| | Dashboard | Attendance screen |
|---|---|---|
| Management (super_admin / admin / manager) | ✅ | ❌ (`tracks_attendance = false`) |
| Sales (`employee`) | ✅ | ❌ (field visits, no fixed hours) |
| Staff (design / photography / video) | ❌ | ✅ |

The backend already refused a check-in for an untracked employee (409
`ATTENDANCE_NOT_TRACKED`, §18); this removes the screen rather than showing a card that
could only fail.

### 12-hour clock

The API speaks **24-hour time throughout** — unambiguous, sorts correctly, and what
`<input type="time">` submits. Display is converted at the edge by
`formatTime12()` in `Frontend/src/components/attendance/attendanceUi.ts`:
`"13:00"` → `"1:00 م"` / `"1:00 PM"`.

Written by hand rather than with `toLocaleTimeString`, which renders eastern Arabic digits
(`١٢:٣٠`) in some browsers — jarring next to the Latin figures used everywhere else in the
app. Nothing stores a formatted time, so this stays a single point of change.

### Files
```
app/Application/Services/WorkScheduleService.php   setEmployeeWeek(), weekFor()
app/Http/Requests/SetEmployeeWeekRequest.php
app/Http/Resources/EmployeeWeekResource.php        employee + week, no template named
app/Http/Resources/EmployeeResource.php            + tracks_attendance
tests/Feature/EmployeeWeekTest.php
Frontend/src/pages/AttendanceSchedulesPage.tsx     rewritten: people, not templates
Frontend/src/components/attendance/attendanceUi.ts formatTime12(), formatSpan()
Frontend/src/components/layout/Sidebar.tsx         hides /attendance when not tracked
```

---

## 21. The employees screen is `employees.view_all` only

`/employees` administers **other people**. An account holding only
`employees.view_own` was still shown the entry, opened it, and found a management list
containing exactly one row — itself. That is the wrong shape for looking at your own
record, so the screen now requires `employees.view_all`:

- `components/layout/Sidebar.tsx` — the nav entry
- `App.tsx` — the route guard, so the direct URL agrees with the sidebar

This affects `staff` (design / photography / video) and `employee` (sales), both of which
hold only `view_own`.

### Where own details live instead
The **Settings** page — which is where an account looks at itself anyway. Its "Current
Account" card gained a read-only *job details* block: department, job title, start date, and
a link to the attendance screen for anyone the module applies to.

It reads `user.employee` from `GET /auth/me`, which already carried it — so no new endpoint,
no new query, and nothing to keep in sync.

### The permission itself is unchanged
`employees.view_own` stays on both roles. It grants reading **your own employee row** through
the API, which is a coherent thing to hold and what `AccessScope` scopes; it simply no longer
implies access to the management screen. The two were conflated before.

---

## 22. The running shift counter

While a shift is open the employee card counts up **7:32:45**, ticking every second.
Once the day is closed it shows **7:32** — the API stores `worked_minutes`, so a seconds
figure after check-out would be invented precision.

Seconds are rendered smaller and muted: they move every tick, and the eye belongs on the
hours.

### How it stays accurate
`AttendancePage` runs one effect per payload:

```
baseline  = today.server_time        (the server's clock — always)
receivedAt = Date.now()              (captured once, when that payload arrived)
elapsed    = baseline + (Date.now() - receivedAt) - check_in_at
```

The device clock only ever measures the *gap since the payload arrived*, never the absolute
time — so a device set wrong still shows the right figure.

Two details that matter only once seconds are visible:

- **The gap is measured, not counted.** An earlier version incremented a counter by 1000 on
  each interval tick. `setInterval` drifts, and a late tick loses a second permanently;
  at minute resolution that was invisible, at second resolution it is not.
- **Background throttling corrects itself.** Browsers throttle timers in hidden tabs, but
  `useAttendanceToday` refetches on window focus, which replaces the payload and resets the
  baseline.

`elapsedSeconds()` floors at zero, so a clock skew that puts the server behind the check-in
shows `0:00:00` rather than a negative figure.

The whole thing is one `useEffect` holding the interval and writing to state — no ref, and
no dependency listed solely to force a re-render.

```
Frontend/src/components/attendance/attendanceUi.ts   elapsedSeconds(), formatHms()
Frontend/src/pages/AttendancePage.tsx                the effect and the display
```

---

## 23. Times must be isolated from the RTL layout

### The bug
The running counter rendered as **`44: 1:09`** — seconds before hours, with the colon
stranded on the far side.

The page is `dir="rtl"`. The hours and seconds were two sibling spans, so the RTL layout
placed them right-to-left, and the Unicode bidi algorithm then moved the leading colon of
`:44` to the opposite end. Nothing was wrong with the arithmetic; the digits were simply
being read as if they were Arabic prose.

**A clock is not a sentence.** `1:09:44` reads left to right in every language.

### The fix
`Frontend/src/components/attendance/Ltr.tsx` — a one-line component wrapping its children in
`<span dir="ltr">`. The `dir` attribute carries `unicode-bidi: isolate` in the UA stylesheet,
which pins the direction *inside* the run without changing how the line as a whole is aligned
in the RTL page. Arabic that travels with the number (the ص / م suffix) still renders
correctly, because isolation only fixes the embedded run.

### Everywhere it applies
The counter was the visible symptom; the same class of bug affected every numeric run built
from more than one token. All seven are now isolated:

| Screen | Run |
|---|---|
| Employee card | the `h:mm` + `:ss` counter |
| Employee card | history row `check-in → check-out` |
| Management grid | scheduled span `8:00 ص – 1:00 م` |
| Management grid | check-in and check-out cells |
| Monthly modal | `check-in → check-out` |
| Schedule editor | each day's `start – end` |

A **single** token such as `7:00` or `8:41 ص` was never affected — one uninterrupted run of
digits renders correctly on its own. The breakage needed two runs, or an arrow between them,
which is why it appeared only once seconds were added.

> Worth remembering when adding any future time display: if it concatenates more than one
> number, wrap it in `<Ltr>`.

---

## 24. A day can be worked in more than one sitting

### The case
An employee checks in, works, checks out. At 20:00 an urgent task arrives and they come
back. Before this, the second check-in was refused with `ALREADY_CHECKED_IN` — a message
that was also untrue, since they had checked *out*.

### The model: `attendance_sessions`
`attendances` stays the day's **summary** — first check-in, last check-out, total worked —
so every existing screen, report, export and snapshot keeps working unchanged. Underneath it,
one row per sitting:

```
attendance_sessions
  attendance_id · sequence · check_in_at · check_out_at · worked_minutes
  is_overtime · overtime_status · reviewed_by · reviewed_at · review_note
```

Existing rows were backfilled into their own session 1 by the migration.

### Overtime is a **return**, not a long day
This is the distinction management asked for, and it is why overtime is **not** derived from
`worked − expected`:

- staying twenty minutes late on a task inside session 1 is **not** overtime;
- coming back in the evening **is** — session 2 and beyond are flagged `is_overtime`.

So no threshold or grace value is needed. Lingering never produces a payable record, which
was the explicit requirement ("ما بدي تحسب التأخير").

The flag is *stored*, not derived from `sequence > 1`, so management can correct a session
someone opened by mistake.

The scheduled break is deducted **once**, from session 1 only — an employee who returns in
the evening does not lose a lunch hour they already took.

### Calculated automatically, owed only when approved
The hours are a fact the moment they are worked; whether they are *owed* is a judgement.
Closing an overtime session sets `overtime_status = pending`.

| Method | Path | Permission |
|---|---|---|
| GET | `attendance/overtime` | `attendance.view_all` |
| PATCH | `attendance/sessions/{session}/overtime` | `attendance.approve` |

Rejecting requires a note. `approved_overtime_minutes` counts only approved sessions, so
nothing is owed by default. An employee cannot approve their own (403), an ordinary session
cannot be approved (409 `NOT_OVERTIME`), and one still running cannot be reviewed
(409 `OVERTIME_IN_PROGRESS`).

`employee_overtimes` is untouched and remains the place for overtime arranged outside
attendance.

### Writers must keep sessions in step
`createManually()` and `correct()` previously wrote times onto the day row alone. Both now
maintain the sessions too — a correction moves the **first** session's start and the **last**
session's end, matching what the summary columns mean. Without this the two would drift and
the next `refreshFromSessions()` would silently undo the correction.

---

## 25. MariaDB silently rewrote every check-in — and the test suite could not see it

### What happened
After the sessions work, a live check produced a check-in **three hours after** its own
check-out. The cause was not in PHP.

```sql
check_in_at  timestamp  NOT NULL  DEFAULT current_timestamp()  ON UPDATE current_timestamp()
```

MariaDB gives the **first `NOT NULL` TIMESTAMP column in a table** an implicit
`DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (unless
`explicit_defaults_for_timestamp` is on). So *any* later update of a session — even one
touching only `worked_minutes` — rewrote the check-in to "now", rendered in the server's
timezone (`SYSTEM` = Damascus, hence the three hours).

`attendances.check_in_at` escaped it only by being **nullable**, which suppresses the
implicit default. It was then corrupted second-hand when `refreshFromSessions()` copied the
poisoned value up.

### The fix
`datetime`, not `timestamp`, for every application-controlled instant:

```php
$table->datetime('check_in_at');
$table->datetime('check_out_at')->nullable();
$table->datetime('reviewed_at')->nullable();
```

DATETIME has no implicit default, no `ON UPDATE`, and no timezone conversion — which is
exactly what an instant the application owns should have.

An audit of every `timestamp` column in the schema found no other offender: `audit_logs.created_at`
and `failed_jobs.failed_at` carry explicit `useCurrent()` defaults and no `ON UPDATE`.

### Why 191 green tests missed it
**The test suite runs on SQLite; development and production run on MariaDB.**
`phpunit.xml` sets `DB_CONNECTION=sqlite` / `:memory:`, while `.env` points at
`mysql`/`phoenitech_sales`. SQLite has no implicit-default behaviour, so the bug was
invisible to every test — including the one that *does* encode the invariant
(`an_employee_who_returns_in_the_evening_records_a_second_session` asserts session 1 still
reads `08:00` after the whole flow, which would have failed on MariaDB).

The coverage was right; the engine was wrong. Note that CLAUDE.md still describes dev as
SQLite — it is MariaDB.

> **Standing rule:** never use `$table->timestamp()` for an instant the application sets.
> Use `datetime()`. And treat a green suite on SQLite as silent about MySQL/MariaDB schema
> semantics.

### Still worth doing
`attendances.check_in_at` / `check_out_at` are nullable `TIMESTAMP`. They have no `ON UPDATE`
hazard and round-trip correctly today, because MariaDB converts session-timezone → UTC on
write and back on read consistently. They remain latently fragile: every stored value shifts
if the server timezone ever changes. Converting them to `DATETIME` is recommended, but the
conversion itself is timezone-sensitive on a table that already holds data, so it should be
done deliberately rather than folded into another change.

---

## 26. Leave: an employee could approve their own — fixed

### The escalation
`POST employees/{employee}/leaves` sat behind `leave.create`, which `staff` holds, and its
inline validation **accepted `status`**. `EmployeeLeaveService` then defaulted it to
`approved`.

So an employee could grant themselves leave outright. Demonstrated live before the fix:
نوال (role `staff`) posted `status: approved` for a fortnight, it was stored approved, and
the management attendance grid showed her days as `leave` instead of `absent` — she could
erase her own absences.

Three things combined to allow it:
1. the route required only `leave.create`;
2. the request accepted a `status` field at all;
3. the service defaulted to `approved` rather than `pending`.

### The model: submitting and deciding are separate acts

| Method | Path | Permission |
|---|---|---|
| GET | `leaves/my` | `leave.view_own\|leave.view_all` |
| POST | `leaves/my` | `leave.create` |
| DELETE | `leaves/my/{leave}` | `leave.create` (own, pending only) |
| GET | `leaves` | `leave.view_all` |
| GET | `leaves/pending-count` | `leave.approve\|leave.view_all` |
| PATCH | `leaves/{leave}/review` | `leave.approve\|leave.reject` |
| GET | `employees/{employee}/leave-requests` | `leave.view_all\|leave.view_own` |
| GET | `leave-types` | `leave.view_own\|leave.view_all\|settings.view` |
| GET | `leave-types/manage` | `settings.view` |
| POST | `leave-types` | `settings.edit` |
| PUT | `leave-types/{leaveType}` | `settings.edit` |
| DELETE | `leave-types/{leaveType}` | `settings.edit` |

The five `leave-types` routes are new in §38; the catalogue behind the picker is a table
now, not a constant.

`leaves/pending-count` returns one number — how many requests await a decision — and
feeds the red badge on the sidebar's **الإجازات** entry. It is declared **before** the
`{leave}` routes so the literal segment is never read as an id, the same ordering
`notifications/unread-count` needs.

The badge is drawn only for an account holding `leave.approve`, which is narrower than
the entry itself: every employee can see `/leaves` for their own requests, but their own
pending request is not a task for them. A red dot someone cannot clear is noise, so the
count is neither fetched nor shown for them.

`StoreLeaveRequestRequest` has **no `status` field and no `days_count`**. The fix is not a
stricter rule — it is that the client has nowhere to put the value. The service sets
`pending` unconditionally, and only `review()` can move it.

The legacy `POST employees/{employee}/leaves` still accepts a status, so it was moved to
`leave.approve`: it is a management tool for recording leave taken before the system
existed, not a request form.

Rejection requires a note. `decided_at` and `decision_note` were added alongside the
existing `approved_by`, so the record says who decided, when, and why.

### Day counting
`days_count` is derived from the employee's **own working week**, not the calendar. A request
spanning a weekend does not spend leave on days off, and a public holiday inside the range is
not spent either — `ScheduleResolver` already knows which days those are. The client cannot
set it.

### Balance
`annual_used` counts **approved** days only; `annual_pending` is shown separately so someone
can see what they have asked for without it being deducted yet.

### Other guards
- Overlapping requests are refused (409 `LEAVE_OVERLAP`); a *rejected* request does not block
  the dates.
- A decided request cannot be decided again (409 `LEAVE_ALREADY_DECIDED`).
- An employee may withdraw a **pending** request, never a decided one — the attendance
  calendar already reflects an approved leave, so management unmakes that decision.
- An employee cannot withdraw someone else's request (403).

### Why this matters to attendance
Approved leave is what stops `attendance:close-day` recording an absence, and what makes the
calendar read `leave`. Both are covered by tests
(`only_approved_leave_shows_on_the_attendance_calendar`,
`an_approved_leave_day_is_never_recorded_as_an_absence`).

### Files
```
app/Application/Services/LeaveRequestService.php     request / review / cancel / balance
app/Http/Requests/StoreLeaveRequestRequest.php       no status, no days_count
app/Http/Requests/ReviewLeaveRequestRequest.php      rejection needs a note
app/Http/Resources/EmployeeLeaveResource.php
app/Http/Controllers/Api/V1/LeaveRequestController.php
database/migrations/2026_09_21_110001_*              decided_at, decision_note
tests/Feature/LeaveRequestTest.php
```

> The frontend for it is `/leaves` — see §33.

---

## 27. UX foundation

### The diagnosis
The design **tokens** were never the problem — `index.css` already defines a full brand
palette, dark/light themes and semantic colours. What was missing sat one level up: every
page invented its own heading, its own counter card and its own "nothing here" message. That
is why the app read as several apps.

The fix is a small set of composition primitives, not a repaint.

### Sidebar: ordered by use, grouped by purpose
Flat list → six groups, most-used first. Empty groups disappear, so the shape of the menu
tells you what your role is.

| Group | Entries |
|---|---|
| *(none)* | لوحة التحكم |
| الدوام | الحضور والانصراف · متابعة الحضور · الدوامات والعطل |
| الأعمال | عملاؤنا · العقود · الاشتراكات · السوشال ميديا |
| التقارير | التقارير · التقارير الأسبوعية |
| الفريق | الموظفون |
| النظام | المستخدمون · الأدوار · سجل التدقيق · الإعدادات |

A `staff` account sees exactly two entries: **الحضور والانصراف** and **الإعدادات**.

The duplicated `NavLink` markup (one copy per section, already drifting apart) was extracted
into a single `SidebarLink`.

### Shared primitives
```
components/ui/PageHeader.tsx    title · subtitle · icon · actions — the top of every page
components/ui/StatCard.tsx      one figure, `tone` mapped to semantic tokens
components/ui/EmptyState.tsx    nothing to show, said deliberately
components/ui/SectionCard.tsx   a titled block; `flush` for edge-to-edge tables
```
`StatCard` takes a `tone` rather than colour classes, so a card reads correctly in both
themes without a second set of styles.

### Every action reports itself — in one place
`lib/apiFeedback.ts` + the axios interceptors.

- **Success:** any non-GET response raises a toast. The verb decides the wording
  (`تمت الإضافة` / `تم التحديث` / `تم الحذف`); `successMessage` on the request config
  overrides it.
- **Failure:** the envelope's `error_code` is translated through `errors.*`, falling back to
  the server's own sentence. 401 is skipped — it already redirects.
- **Opt out** with `silent: true` on the request config.

Wiring this into the interceptor rather than into ~60 call sites is the point: **an action
cannot be added without feedback, because feedback is not the caller's job.**

All **38** error codes the backend can emit are translated in both `ar.json` and `en.json` —
verified by cross-referencing every `BusinessRuleException` code in `app/` against the
locale files. Previously these surfaced in English.

`Toaster` is built on the project's tokens rather than a toast library, so it inherits the
themes and RTL for free. A third-party widget with its own palette would have been the one
thing on screen that did not belong — which is the opposite of what was asked for.

### Check-out asks what was done
`components/attendance/CheckOutDialog.tsx`. Check-out is the one moment the employee is
certainly at the screen and certainly remembers, so it is the cheapest place to capture it.
The answer becomes the session's `notes`, which management sees beside the hours — and for a
return in the evening it is the record of *why* the extra work happened, which is what an
approval decision rests on. The field stays optional; making it mandatory only teaches
people to type a full stop.

### Rollout
`AttendancePage` has been moved onto the primitives as the reference implementation, and its
inline error block removed now that failures arrive as toasts.

**Still to migrate:** Dashboard, Companies, Contracts, Subscriptions, SocialMedia, Reports,
WeeklyReports, Employees, Users, Roles, AuditLog — plus the two remaining attendance screens.
The foundation is in place; the remaining work is applying it page by page.

### Batch 2 — migrated pages

`DashboardPage`, `AttendanceManagePage` and `AttendanceSchedulesPage` now use the shared
primitives. Three things found while migrating, all fixed:

**A dead filter on the dashboard.** The employee list ran
`.filter(emp => !emp.department || emp.department === 'sales' …)` against a field the API
never returns — so it passed everything while looking deliberate. The backend already
restricts that list. Removed.

**A type that hid it.** `DashboardData.employee_monthly_contracts` was missing
`contracts_prev_month`, `sales_prev_month` and `collected_prev_month`, which the API has
always returned. An `any` cast at the call site was covering for it, which is why the gap
survived. The type now matches the payload.

**21 one-sided translation keys that were actually used**, rendering as raw keys
(`contract.status_active`) in one language — including the contract and payment status
labels inside the shared `Badge`, i.e. on every contract and payment row. All filled; a
cross-check of every key used in `src/` against both locale files now reports none missing.

Decorative colour was also removed where it carried no meaning: the dashboard's employee
cards cycled through four ring colours and glows per person. Colour now appears only where
it says something — whether this month's sales beat last month's.

**Remaining:** Companies, Contracts, Subscriptions, SocialMedia, Reports, WeeklyReports,
Employees, Users, Roles, AuditLog.

---

## 28. Two rendering bugs, both system-wide

### Arabic reordered clock times — `dir="ltr"` was not enough

"8:00 ص – 1:00 م" rendered scrambled in the schedule column even though it sat inside an
LTR isolate (§23).

The cause is Unicode bidi **rule W2**: a European number is retyped as an *Arabic* number
when the nearest preceding strong character is an Arabic letter. The `ص` of the first time
makes the `1:00` of the second an Arabic number, which then joins the right-to-left run and
is laid out backwards — dragging the dash with it. Verified against the rule directly: in
the whole string the digits `100` are retyped; isolating each time retypes nothing.

A wrapper around the *whole* range cannot fix this, because the interference happens inside
it. Each time needs its own isolate.

`components/ui/TimeText.tsx`:
- `<TimeText>` — one time in its own `<bdi dir="ltr">`. The explicit `dir` matters: `<bdi>`
  defaults to `auto`, which would see the leading `ص` and resolve the whole token RTL.
- `<TimeRange>` — two times and a separator laid out with **flexbox inside an LTR
  container**, so their order is decided by the box model rather than by bidi resolution,
  and the Arabic suffixes cannot influence it at all.

Every clock time in the app now goes through these; no page formats one itself.

### Arabic clipped inside inputs, and dropdowns had no arrow

`.input-field` set `py-2.5` while `Input`/`Select` set `h-10`. That left ~18px of content
box for a 24px line: fine for Latin, but Arabic descenders (ل ن ي ج) were cut — which is why
**«الكل» read as «الكا»**.

A fixed-height single-line control centres its own text, so the vertical padding was only
ever squeezing it. `.input-field` now sets `padding-block: 0` with `min-height: 2.5rem`, and
`textarea.input-field` keeps its padding since it is genuinely multi-line.

`Select` also carried `appearance-none`, which strips the browser's arrow — leaving a
dropdown that looked exactly like a text field. A chevron was added on the logical **end**
side (so it follows RTL/LTR) with `pe-10` reserving room, so a long Arabic option is never
drawn underneath it.

Both fixes are in shared code, so all nine pages using `Select` get them at once.

### `.input` was never a class

Fifteen controls in Subscriptions were written with `className="input"`. **No such class
existed**, so they rendered with raw browser styling — one of the louder reasons the app
looked like several apps. `.input` is now an alias of `.input-field`; the call sites can be
normalised as those pages are migrated.

### A range follows the reading direction; a clock does not

The first fix forced the whole range LTR, which put the **end** time first — backwards for
an Arabic reader, who starts on the right.

The two levels pull opposite ways and both are right:

| | Direction | Why |
|---|---|---|
| Each time | always LTR | a clock reads `8:00`, never `00:8` |
| The pair | inherits the page | Arabic starts on the right, so the **start** time belongs there |

`TimeRange` therefore sets no `dir` of its own. Flexbox orders the parts along the inherited
direction, so the Arabic suffixes still cannot reorder anything, while the eye meets the
start time first in both languages.

`separator="arrow"` resolves to `←` in Arabic and `→` in English — an arrow that pointed the
wrong way would contradict the layout it sits in.

> **Standing rules.** Never render a clock time as a bare string in an RTL layout — use
> `<TimeText>` / `<TimeRange>`. Never pair a fixed height with vertical padding on a
> single-line control. Force LTR on a *value*, never on a *sequence of values*.

### Still outstanding
Twenty raw `<select>` elements in SocialMedia, Subscriptions and EmployeeProfileModal bypass
the shared component and so get the padding fix but not the chevron. They are converted when
those pages are migrated.

---

## 29. Batch 3 — and the real reason pages looked different

### The finding
A scan for Tailwind palette classes (`text-blue-400`, `bg-emerald-500`, …) as opposed to the
project's semantic tokens returned **279 occurrences across 14 files**.

This is the substance of "every page has its own spirit", and it is worse than
inconsistency: **a palette value cannot respond to the theme.** `--color-success-text` is
`#34d399` in dark and `#057a55` in light; `text-emerald-400` is `#34d399` in both. Every one
of these is correct in one theme and wrong in the other.

### Fixed in this batch

**The attendance module first** — it contributed 27 of them, all mine. `STATUS_STYLES` and
`LOCATION_STYLES` now resolve entirely through tokens, and two design decisions came out of
it:

- `day_off` and `pending` are deliberately **neutral**. Nothing happened on those days, and
  colour spent on nothing is colour that means less everywhere else.
- **Office is neutral; remote carries the colour.** A badge that lights up for everybody
  tells you nothing — now the eye is drawn only to the days someone is away.

**Contracts and Companies** moved onto `PageHeader` and `SectionCard`, and their hand-written
search inputs onto `input-field`. Both carried `focus:ring-blue-500` — **a blue focus ring in
a teal application**, from no palette this project defines. None remain anywhere.

Their headings also used `text-2xl font-black uppercase tracking-wider` against
`PageHeader`'s `text-2xl font-bold tracking-tight`; `uppercase` does nothing to Arabic and
shouts in English.

```
palette colours   279 across 14 files  ->  142 across 10 files
```

### Remaining
EmployeeProfileModal (66), SocialMedia (45), Employees (45), Subscriptions (32), and small
counts in WeeklyReports, Settings, Reports, Login. These are converted as each page is
migrated — the token names map one-to-one, so it is mechanical rather than a redesign.

> **Standing rule.** Never use a Tailwind palette colour in a component. The semantic tokens
> (`success` / `danger` / `warning` / `info` / `primary`, plus `surface` / `text` / `border`)
> are the only colours that exist in both themes.

---

## 30. AM/PM everywhere, and the end of raw palette colours

### AM/PM in both languages
`formatTime12` now emits `8:00 AM` regardless of locale.

Beyond being what management asked for, it removes the bidi problem at its source: `ص` and
`م` are strong right-to-left characters, and a strong Arabic letter beside digits is exactly
what makes the algorithm retype the next number and lay the run out backwards (§28). `AM`
and `PM` are Latin, so a time is one left-to-right run with nothing to reorder.

Midnight and noon verified: `00:00 → 12:00 AM`, `12:00 → 12:00 PM`.

`TimeText` no longer takes a locale. `TimeRange` still does — the range order and its arrow
follow the reading direction (§29).

### Categorical tokens
Converting the remaining colours revealed they were doing **two different jobs**:

- **categorical** — five departments, several content types, distinguished by colour;
- **semantic** — paid, overdue, remaining.

Mapping both onto `success` / `danger` / `warning` / `info` would have collapsed five
distinguishable departments into one colour, and would have said things that are not true
("design is a success").

So a categorical set was added: `cat-1` … `cat-5`, each with a `-text` and `-bg`, defined for
both themes and registered in `@theme`. They carry no judgement — they exist only so a
category can be told apart while still adapting to the theme, which the palette values they
replace never did.

### Result
```
palette colours   279 across 14 files  ->  0
```

The conversion was done as explicit pairs across three passes rather than one regex sweep,
precisely because a sweep would have flattened the categorical uses.

Also folded in: `ring-blue-500` on a focused control became `ring-primary-500`, and the
report tabs' active underline became the brand colour instead of blue.

> **Standing rule (now enforceable).** A Tailwind palette colour in a component is a bug.
> Use `success` / `danger` / `warning` / `info` / `primary` for states, `cat-1…5` for
> categories, and `surface` / `text` / `border` for everything else.

---

## 31. Two faults behind one symptom: "design · 5 أيام" ran together

### The missing translation
`employee.department_*` **did not exist in either locale.** Four places called it —
the attendance filter, the schedule card (twice) and Settings — and every one fell through
to the raw column value, printing `design` instead of `تصميم`.

The keys were never added because `EmployeesPage` carries its own `DEPARTMENTS` constant with
`label_ar` / `label_en` baked in, so the gap was invisible from that page. Added for all six
departments in both locales; the constant in `EmployeesPage` remains the odd one out and
should fold into i18n when that page is migrated.

### The collision
Even in Arabic the line would still have broken, because the fault is structural.

`"design · 5 أيام"` written as one string: `design` is strong left-to-right, `5` is a
number, and only a neutral `·` separates them. The bidi algorithm resolves the neutral
towards the surrounding direction and **merges the two into a single left-to-right run** —
they end up side by side and read as one value. Verified by walking the runs: the line
resolves to `L:"design · 5"` + `R:"أيام"`.

`components/ui/MetaLine.tsx` fixes it the same way `TimeRange` does: each fact is its own
`<bdi>`, and the separators are elements rather than characters inside the text, so order
comes from the box model and follows the reading direction.

Applied to the three places that had this shape — the schedule card, the holiday row
(`date · repeats annually`) and the Settings identity line (an Arabic name beside a Latin
`@handle`).

> **Standing rule.** A line of facts joined by a separator is a **list**, not a sentence.
> Use `<MetaLine>`; never build one by concatenating strings.

---

## 32. Roles restructured — Phase 20

### What was wrong

Five separate things had grown into one knot, and the effect was that nobody could say what
a role meant without checking the seeder.

1. **`admin` and `super_admin` held identical permission sets.** Two names, one level of
   access — a distinction the system never enforced.
2. **`hr` held 24 permissions and had zero users.** An unused role with real access is a
   standing invitation to hand it out without thinking.
3. **The word "employee" meant three different things.** The `employees` table held all
   twelve people; the `employee` *role* meant the two in sales; and the designers, who are
   obviously employees, held a role called `staff`.
4. **`department = 'sales'` and the role `employee` encoded the same fact** in two places,
   free to disagree.
5. **Departments were free text.** Three lists existed in the frontend and disagreed — the
   employees screen had no `video`, the attendance screen had no `dev` — and the API
   validated none of them, so a typo created a department no filter could ever match.

### The rule that resolves it

> A **role** says what an account may *reach*. A **department** says what the person *does*.

Departments do not get roles for being departments. Design, development, photography and
video need identical access, so they share `team` and differ by department. Marketing owns
the social-media module, so it genuinely differs and is a role. Sales and sales management
differ in access, so they are two roles.

### The migration

`RolePermissionSeeder::migrateLegacyRoles()` renames rather than recreates, which is the
part that matters: spatie keys both `role_has_permissions` and `model_has_roles` on the
**role id**, so renaming the row carries every assignment across untouched. Creating new
roles beside the old ones would have silently stripped all fifteen users.

| Was | Is | Why |
|---|---|---|
| `employee` | `sales` | It always was the sales role; the name said otherwise |
| `staff` | `team` | They are employees too |
| `manager` | `general_manager` | |
| `admin` | merged into `general_manager` | Identical to super_admin, so it drew no real line |
| `hr` | deleted | 24 permissions, 0 users |
| — | `sales_manager` (new) | مدير مبيعات had no role of its own |
| — | `marketing` (new) | تسويق owns social media |

Verified against the live database after seeding: 15 users, none orphaned
(super_admin 2 · general_manager 2 · sales 2 · team 9).

### Departments

`Employee::DEPARTMENTS` is now the one list, enforced by `Rule::in` on both the store and
update requests. `Frontend/src/constants/departments.ts` mirrors it and holds **no labels** —
those come from `employee.department_<id>` in both locales, which is precisely what the old
per-page constant's baked-in `label_ar`/`label_en` had been hiding.

One behavioural fix fell out of it: the employees table resolved an unknown department with
`DEPARTMENTS.find(...) || DEPARTMENTS[0]`, so anyone with no department was displayed as a
designer. It now renders `—`.

### Tests

`tests/Feature/RoleStructureTest.php` pins the boundaries that carry meaning rather than
permission counts, which are meant to be edited from the dashboard:

- the role set is exactly those six, and the five replaced names are gone;
- reseeding over a legacy `staff` role carries that user across to `team`;
- `general_manager` holds every operational permission and **no** `users.*` / `roles.*`;
- **no role but `super_admin`** can create users or assign permissions;
- `team` reaches nothing commercial — the original requirement, now asserted;
- an unlisted department is rejected, and every listed one is accepted.

### One screen: الفريق

«the employees» and «the users» were two lists with nothing stating how they related, which
is what made the pair hard to reason about. They are now one screen at `/team`
(`Frontend/src/pages/TeamPage.tsx`; `/employees` redirects, so old links keep working), with
the account's role and login state as columns beside the department.

To make that possible, `EmployeeResource` carries an `account` block — **gated on
`users.view`**. That gate matters: the endpoint must not become a side door through which
someone who cannot open the accounts screen learns a colleague's role anyway. The key is
omitted entirely rather than nulled, so `undefined` means *not permitted* and `null` means
*this person has no login*.

The few accounts belonging to no employee (the owner account, the technical one) are a
footnote under the table, not a screen: `GET users?without_employee=1`, rendered by
`components/team/SystemAccounts.tsx`. The sidebar entry for «the users» is gone; account
management is reached from a link inside that section.

> **Note (open).** `antoine.haddad@` and `rand.ahmad@` have **no employee profile**, so they
> currently appear only in the system-accounts footnote rather than in the team list. They
> are real people and almost certainly should have profiles — but creating them means
> deciding salary, leave allowance and whether attendance applies, so it is left to the user.

> **Standing rule.** Before adding a role, ask whether it needs *different access* or only a
> *different name*. If it is the name, it is a department.

---

## 33. Working somewhere else for a day, and the leave screen

Two gaps closed together, both about the same thing: the schedule says what *usually*
happens, and people needed a way to say what happened *today*.

### 33.1 A one-off change of place

`work_schedule_days.location` is a weekly template — "Sabine works Mondays from home". It
had no notion of an exception, so someone staying home for one day had nowhere to record it
and the grid showed them in the office.

**Who decides.** The employee, at check-in, with management able to correct it afterwards.
Not an approval flow: someone who wakes up unwell and decides to work from home cannot wait
for a manager before pressing the button, and the choice changes nothing that is owed.

**Why that is safe.** `PunchAttendanceRequest` still refuses a time, a date, an employee id
and a status — the things that could be used to fake hours. `location` is the one fact the
server cannot derive, and it moves nothing: `expected_minutes`, `status`, `scheduled_start`
and `scheduled_end` are untouched by it. It is visible to management the moment it is set.

**The two columns.** `attendances.location` is now *where the day was actually worked*, and
`attendances.scheduled_location` is *where it was due to be*. Both are snapshotted when the
day is recorded, so an exception still reads as an exception after the template is edited —
the same reason every other schedule field is snapshotted. `Attendance::isLocationException()`
is the two differing, and the resource exposes it as `is_location_exception`.

A correction may change `location`. It may **not** change `scheduled_location`: rewriting
what was expected would erase the very thing that made the day an exception.

| | before | after |
|---|---|---|
| `POST attendance/check-in` | `notes` only | `notes`, optional `location` (`office\|remote`) |
| `PATCH attendance/{id}` | times, status, notes | + `location` |

The employee screen keeps the common case at one tap: the primary button checks in wherever
they were due to be, and a quieter second button underneath offers the other place.

### 33.2 The leave screen

The request/approval API had been complete and tested since §26 with **no UI at all**, which
is why nobody in the company knew leave existed as a feature. `/leaves` is that UI.

| Tab | Permission | What it does |
|---|---|---|
| إجازاتي | `leave.create` | balance, request form, withdraw a request not yet decided |
| الطلبات | `leave.approve\|leave.reject` | pending first, approve or reject with a note |

The tab strip disappears when only one of them applies, so most employees see just their own
and a manager sees both. The page cannot approve its own request — the decision is a separate
endpoint, and `StoreLeaveRequestRequest` has nowhere to put a status.

Every leave mutation invalidates the attendance queries too, because an approved request
turns those days from absences into `leave` on the calendar; without it the two screens
disagree until a refresh.

**No new permissions.** `leave.*` were already seeded in §26; this is the screen that finally
uses them.

---

## 34. Four corrections from using §33

### 34.1 Management does not request leave from itself

`general_manager` holds `leave.create` — it must, to record leave *for* someone —
and the first version of `/leaves` read that as "show them a personal tab". So the
person who approves requests was also being offered a request form, a loop with no
second person in it.

The personal tab now also requires `employee.tracks_attendance`, the same flag that
keeps management and sales off the attendance screen. Management sees the inbox and
a **تسجيل إجازة لموظف** button beside it: leave agreed outside the system, entered
already approved. Two different acts, two different buttons, two different endpoints
(`POST leaves/my` vs `POST employees/{employee}/leaves`).

### 34.2 The same three days cost two days or three

`EmployeeLeaveService::createLeave` counted **calendar** days
(`diffInDays + 1`) while `LeaveRequestService` counted **working** days. Leave
entered by management over a weekend therefore charged days the employee was never
due to work, and the same stretch cost a different amount depending on who typed it.

The count now lives in `App\Application\Support\LeaveDays` and both services call it.

### 34.3 "What did you do today?" was optional, and invisible

Two faults, one symptom — management could not review a day:

1. The field was optional at check-out, so most days had no answer.
2. The answer is stored on the **session**, not the day's row, and `dayGrid()` never
   loaded the sessions — so even a day that had one could not be shown.

`CheckOutAttendanceRequest` (separate from `PunchAttendanceRequest`, which still
serves check-in) makes it required with `min:3`. `dayGrid()` eager-loads `sessions`,
and `AttendanceResource` exposes them plus a `tasks` line joining them.

A derived row — a day nobody recorded — gets an empty sessions relation rather than
an unloaded one, so `tasks` is present and null instead of missing from the payload.

In the grid it is a button, not a column of text: a day's account of itself runs to a
paragraph. `TasksDialog` opens it **per sitting**, because a day with an evening
return has two answers written at two different moments and which hours the work
belongs to is most of what is being read for.

> The old code comment argued a mandatory field "would only teach people to type a
> full stop". That was overruled: a day with no account of itself is not reviewable.
> `min:3` is the compromise.

### 34.4 Contract figures follow the department, not the viewer

The team table showed a contracts column for everyone, so design, photography, dev
and video read as `0` — which looks like a performance figure rather than "does not
apply". Contracts belong to sales and management.

`COMMERCIAL_DEPARTMENTS` in `constants/departments.ts` is the single list, and
`hasContracts(department)` the check. Outside it the table cell is a dash and the
**الأداء والعقود** tab is absent from the profile.

This is about the **subject** of the screen. Who may see contract figures at all
remains a permission (`contracts.view_all`) and a separate question.

---

## 35. The check-in control offered a choice the server ignored

### 35.1 Two rival buttons, one of them a lie

§33.1 put the location exception underneath the check-in button as a second
button: **تسجيل دخول** and, below it, **اليوم أعمل من البيت**. Two faults came out
of that shape:

1. It was not clear which one actually started the day. The primary button did not
   say where it was checking you in *from*.
2. On an **evening return** the server ignores `location` entirely — the place
   belongs to the day and is fixed by the first check-in, since the row has one
   `location` column, not one per sitting. So the second button was offered and
   did nothing.

The place is now chosen *before* the action: a two-option control seeded from the
schedule, then one button. On a resume (`next_is_overtime`) the control is absent
and the button reads **العودة للعمل**, because there is nothing left to choose.

`AttendanceService::checkIn()` documents why the resume path drops `$location`, and
a test pins it: a morning in the office stays an office day however the evening
return is recorded.

### 35.2 Leave was three clicks away from the screen that needed it

Someone who has just realised they need a day off is looking at the attendance
card, not the sidebar. `/attendance` now carries a **طلب إجازة** action in its
header, behind `leave.create`.

### 35.3 The week and month views

`GET attendance/summary` (`attendance.view_all`) returns **one row per tracked
employee** over a range — `TeamSummaryRequest` extends `AttendanceHistoryRequest`
so the range parsing is not written twice, and `teamSummary()` is built on
`employeeSummary()` so a figure here can never disagree with the same figure in
one employee's own calendar.

Deliberately not seven daily grids: over a range the question is "who is short of
hours and who was absent", which is a question about people, not days. The table
sorts by shortfall descending by default — the rows the view was opened to find.

The day view keeps its counters and status filter; both describe a single day and
are hidden over a range.

### 35.4 Search and sorting

The grid was name-ordered with no search, which stops scaling around twenty
people. `utils/sorting.ts` holds the comparator and `SortableHeader` the heading;
they are separate files because a module exporting both a component and plain
functions breaks Vite's fast refresh.

Two details in the comparator worth keeping: empty values always sort **last**
regardless of direction (someone who never checked in is missing a time, not
holding the earliest one), and hours sort on `worked_minutes` rather than the
formatted `worked_hours`, where "10:00" would sort before "9:00".

> **Still missing a UI:** overtime approval. `GET attendance/overtime` and
> `PATCH attendance/sessions/{session}/overtime` are complete and tested, and the
> check-out dialog already promises the employee that management will review it —
> but there is no screen. Same gap leave had before §33.2.


---

## 36. Tasks — assigning work and following it

Five new permissions (66 → 71), one module, and one distinction that the rest of
the design follows from.

### 36.1 Moving a task is not editing it

```
tasks   view_all  view_own  create  edit  delete
```

`tasks.edit` is reassigning work, redating it, rewriting what it asks for.
Reporting that you have started something and finished it is a different act by a
different person, so `PATCH tasks/{task}/status` sits behind
`tasks.view_all|tasks.view_own` and the controller then checks the mover is
either management or the person the task belongs to
(`AccessScope::canAccessEmployee($user, $task->assigned_to, 'tasks.edit')`).

Put the status behind `tasks.edit` instead and one of two things happens: either
nobody on `team` can ever say they finished anything, or everybody on `team` can
reassign the company's work to themselves. The split is what avoids both.

| Endpoint | Permission | Who, in practice |
|---|---|---|
| `GET tasks` | `tasks.view_all\|tasks.view_own` | everyone; rows scoped by `AccessScope` |
| `GET tasks/summary` | `tasks.view_all\|tasks.view_own` | same scope as the listing |
| `GET tasks/{task}` | `tasks.view_all\|tasks.view_own` | + own-task check in the controller |
| `POST tasks` | `tasks.create` | management |
| `PATCH tasks/{task}` | `tasks.edit` | management |
| `PATCH tasks/{task}/status` | `tasks.view_all\|tasks.view_own` | the assignee, or management |
| `POST tasks/{task}/comments` | `tasks.view_all\|tasks.view_own` | anyone who may see the task |
| `DELETE tasks/{task}` | `tasks.delete` | `general_manager` and above |
| `GET tasks/checklist` | `tasks.view_all\|tasks.view_own` | always the caller's own lines — see §39 |
| `GET tasks/pending` | `tasks.view_all\|tasks.view_own` | always the caller's own untouched tasks — see §40 |
| `POST tasks/{task}/items` | `tasks.view_all\|tasks.view_own` | the assignee, or management |
| `PATCH tasks/{task}/items/{item}` | `tasks.view_all\|tasks.view_own` | the assignee, or management |
| `DELETE tasks/{task}/items/{item}` | `tasks.view_all\|tasks.view_own` | + the line must be one the caller wrote |

`tasks/summary`, `tasks/checklist` and `tasks/pending` are all declared
**before** `tasks/{task}` — otherwise the word is read as an id and they 404.

**No new permission.** The checklist is four endpoints and zero additions to the
catalog, which is the point: ticking off a step of the work you were asked for is
covered by the permission that already lets you report the task finished.

Role grants: `general_manager` everything (it takes the whole catalog minus
`users.*`/`roles.*`); `sales_manager` gets all but `tasks.delete` — it runs the
board without erasing its history; `sales`, `marketing` and `team` hold
`tasks.view_own` only.

### 36.2 Four states, and nothing the client may back-date

`todo → in_progress → done`, plus `cancelled`. Every extra column on a board is a
decision somebody must make before they can move a card, and these four are the
only distinctions that change what anyone does.

**Overdue is not a state.** It is a fact about an open task whose date has passed,
derived in `Task::isOverdue()` and sent as `is_overdue`, so the counters, the row
colouring and the ordering can never disagree — and nothing has to be recomputed
nightly.

`started_at` and `completed_at` are written by `TaskService::changeStatus()`, not
accepted from the client, and a task always enters as `todo` whatever `POST
tasks` was sent (`StoreTaskRequest` has no `status` field at all — the same shape
as `StoreLeaveRequestRequest`, and for the same reason). Reopening clears the
completion: a task that is running again is not one that finished yesterday.

Two refusals worth knowing, both 409 `BusinessRuleException`:
`TASK_STATUS_UNCHANGED` (moving a task to the state it is already in) and
`TASK_CLOSED` (editing a finished task — reopen it first, so the edit is on the
record).

Cancelling requires a note (`UpdateTaskStatusRequest`); the note is also written
onto the comment thread, so a task reads as one story rather than a field nobody
opens.

### 36.3 One assignee, and a thread

`assigned_to` is a column, not a pivot table. Shared ownership reads well on a
board and fails in practice — when two people own a task, nobody does.

`task_comments` is append-only: there is no update or delete endpoint, because a
thread people can rewrite is not a record of anything. It stores `author_name`
alongside `user_id` so a comment still says who wrote it after the account is
gone. `TaskComment` is deliberately **not** `Auditable` — the comment *is* the
record; auditing it would write every line into the trail twice.

### 36.4 Key files

```
database/migrations/2026_09_22_120001_create_tasks_table.php
database/migrations/2026_09_22_120002_create_task_comments_table.php
app/Models/Task.php · TaskComment.php
app/Application/Services/TaskService.php
app/Http/Controllers/Api/V1/TaskController.php
app/Http/Requests/StoreTaskRequest.php · UpdateTaskRequest.php
                  UpdateTaskStatusRequest.php · StoreTaskCommentRequest.php · TaskFilterRequest.php
app/Http/Resources/TaskResource.php · TaskCollection.php · TaskCommentResource.php
tests/Feature/TaskTest.php                     (20 tests)
Frontend/src/pages/TasksPage.tsx · api/tasks.ts · types/tasks.ts
```

---

## 37. Tasks, continued — the dashboard, the bell, and files

Three additions on top of §36, each independent of the others: tasks now appear
on the landing screen, work that happens to you is announced, and a task can
carry files. One new permission (71 → 72).

### 37.1 The dashboard leads with what is late

`GET dashboard` now returns a `tasks` block beside `stats` and `charts`:

```jsonc
"tasks": {
  "scope": "all" | "own",       // whose board this is
  "summary": { "overdue": 3, "due_today": 1, ... },
  "focus":   [ /* up to 5 open tasks, most pressing first */ ]
}
```

It is composed by `TaskService::digest()` and scoped by the same
`AccessScope::ownEmployeeId($user, 'tasks.view_all')` the listing uses — so the
numbers on the dashboard and the numbers on `/tasks` can never disagree. The
block is **null** for an account holding neither task permission, and the screen
then renders no task section at all: an empty one would read as "nothing to do".

`scope` is sent rather than inferred on the client because the sentence above
the block depends on it. "3 overdue" means something different to a manager
reading the company's board and to the person holding all three.

Note the ordering on the page: the task block sits **above** the financial KPIs.
The money is a year in review; the tasks are today, and they are the only thing
on that screen anyone can act on within the hour.

`dashboard.view` still guards the endpoint, so `team` and `marketing` do not
reach it — they land on attendance, and read their tasks on `/tasks`.

### 37.2 Notifications — one new permission, and no stored sentences

```
notifications   view
```

Every role holds it, including `team`. It is still a permission rather than
"any authenticated account" for two reasons: the catalog stays the single list
of what exists in the system, and the bell can be taken away from a role from
the admin screen instead of by a code change.

There is no `view_own` / `view_all` split here, and there will not be one. Every
endpoint reads the caller's own feed — the user id comes from the token, never
from the request — so there is no wider version of this permission to grant.

| Endpoint | Permission | Notes |
|---|---|---|
| `GET notifications` | `notifications.view` | paginated, newest first, own feed only |
| `GET notifications/unread-count` | `notifications.view` | the number on the bell |
| `PATCH notifications/read-all` | `notifications.view` | clears the bell |
| `PATCH notifications/{notification}/read` | `notifications.view` | 404 for somebody else's |

`unread-count` and `read-all` are declared **before** `{notification}`, for the
same reason `tasks/summary` is: otherwise the word is read as an id.

**Nothing is stored as a sentence.** The row keeps a `type` and the facts behind
it (`data`), and the bell renders the line in the reader's language from
`notification.type_*` in `ar.json`/`en.json`. An Arabic string written into the
database at send time would still be Arabic after the reader switches to
English, forever — and half this company reads each.

Three rules live in `NotificationService`:

1. **Nobody is notified of their own action.** `push()` takes the actor and
   drops the notice when it would land back on them. One rule in one place
   rather than one rule per feature that forgets it.
2. **A notice never fails the thing that caused it.** An assignee with no login
   is simply not told; the task is still assigned.
3. **A notice dies with its subject.** `purgeSubject()` runs when a task is
   deleted — the subject is polymorphic, so no foreign key can cascade it, and a
   notification outliving its task is a bell that leads nowhere.

What is announced: a task assigned or reassigned (to the new owner), a status
move and a comment (to the assignee and the person who asked for the work,
whoever did not do it), and — from `tasks:remind`, daily at 07:30 — what is due
today and what is already late. That job is idempotent per task per day
(`pushOncePerDay`), so a catch-up run never buries anybody.

Each notice carries `link: /tasks?task={id}`; the tasks screen reads the
parameter, opens that task, and then clears the query string so a later visit
does not reopen a dialog somebody already dealt with.

### 37.3 Files on a task

```
POST   tasks/{task}/attachments                tasks.view_all|tasks.view_own
DELETE tasks/{task}/attachments/{attachment}   tasks.view_all|tasks.view_own
```

Behind view rather than `tasks.edit`, and then `AccessScope::canAccessEmployee(…,
'tasks.edit')` in the controller — the same split as `PATCH status` and for the
same reason: **handing back the work you were asked for is doing the task, not
editing it.** An assignee who cannot reassign work can still attach the design
they were asked to produce.

Deletion goes through the relation (`$task->attachments()->findOrFail($id)`), so
an id belonging to another record's attachment is a 404 here rather than a
deletion over there. Deleting a task deletes its files from disk as well as
their rows; nothing in the database does that for a polymorphic relation.

**A bug fixed on the way.** `POST attachments` wrote the client alias
(`'contract'`) into `attachable_type`, while `$contract->attachments()` — like
every Eloquent morph relation — looks for the class name. Every file uploaded
from the contract screen was therefore stored correctly and then never shown
again. The alias→class mapping now lives on `Attachment::ALIASES` and is applied
in `UploadAttachmentRequest::attachableClass()`; migration
`2026_09_22_130002_normalize_attachment_attachable_types` rewrites the existing
rows so those files reappear.

### 37.4 Key files

```
database/migrations/2026_09_22_130001_create_notifications_table.php
                    2026_09_22_130002_normalize_attachment_attachable_types.php
app/Models/Notification.php · User::notifications() (overrides Notifiable's)
app/Application/Services/NotificationService.php
app/Application/Services/TaskService.php          (digest, announce, attach/detach)
app/Console/Commands/RemindTaskOwners.php         (tasks:remind, 07:30 daily)
app/Http/Controllers/Api/V1/NotificationController.php
app/Http/Requests/NotificationFilterRequest.php · StoreTaskAttachmentRequest.php
app/Http/Resources/NotificationResource.php · NotificationCollection.php
tests/Feature/NotificationTest.php                (15 tests)
tests/Feature/TaskAttachmentTest.php              (9 tests)
Frontend/src/components/layout/NotificationBell.tsx
Frontend/src/components/tasks/TaskDigest.tsx
Frontend/src/api/notifications.ts · types/notifications.ts
```

---

## 38. Leave types stopped being a constant

**No new permissions.** Reading the catalogue rides on `leave.view_own`, which everyone who
may file a request already holds; editing it is `settings.edit`, which `super_admin` and
`general_manager` already hold. Adding a `leave_types.*` group would have meant four more
permissions to grant, revoke and explain, for an act nobody performs who is not already
configuring the system.

### 38.1 The problem

The five leave types — annual, sick, unpaid, emergency, special — were written into the
code in **three places at once**: `EmployeeLeave::TYPES`, an `enum` column on
`employee_leaves.leave_type`, and a TypeScript union in `Frontend/src/types/models.ts`.
Adding a type meant a migration and a deployment; removing one was not possible at all.
A company that never grants "special" leave still had it on every request form.

Worse, the types were not equal in the code the way they were on screen. `annual` was the
only one the balance deducted, because four services asked the question by writing the
literal string `'annual'` into a query. `special` was counted by nothing at all: it could
be recorded and then vanished from every summary.

### 38.2 The shape of the fix

`leave_types` is now a table, and `employee_leaves.leave_type` stores its `key` as a plain
string. Three decisions are worth keeping in mind:

- **No foreign key.** A leave record is history, and it must outlive the catalogue entry it
  names. The service is what stops a type being deleted while records point at it; the
  database is deliberately not asked to cascade anything.
- **`key` is derived, never sent and never edited.** `LeaveTypeService::deriveKey()` slugs
  the English name and disambiguates a collision (`annual` → `annual_2`). Neither
  `StoreLeaveTypeRequest` nor `UpdateLeaveTypeRequest` has a `key` field, so a client cannot
  file a new type on top of another type's history.
- **Names live in the database, not in `ar.json` / `en.json`.** A type an admin adds has no
  translation key to add, so each row carries `name_ar` and `name_en`. The five old
  `leave.type_*` keys were removed from both locale files. UI chrome around the catalogue
  is still translated as usual, under `leave_type.*`.

Seeded **in the migration**, not a seeder: the request form cannot render without at least
one type, so no environment — including a fresh test database — may exist without them.

### 38.3 `deducts_from_allowance` — the only behaviour a type carries

Everything else about a type was always identical: the same approval flow, the same
working-day counting, the same effect on the attendance calendar. The one real difference
was whether approved days come off the employee's yearly allowance, and that is now a
column rather than the word `annual` in five separate queries:

```
LeaveType::deductingKeys()   ← the single place that answers it
  LeaveRequestService::balance()
  EmployeeLeaveService::getSummary()
  EmployeeService::list() / stats() / overallStats()
  Employee::getApprovedAnnualLeaveDaysAttribute()
```

Not memoised on purpose — a stale cache here would quietly misreport people's balances, and
the table holds a handful of rows.

Both summaries gained a `by_type` array covering the whole catalogue, and lost the
hard-coded `sick_days` / `unpaid_days` / `emergency_days` keys that named four of the five
types and forgot the fifth.

### 38.4 The two guards

| Attempt | Result |
|---|---|
| Delete a type that leave records use | 409 `LEAVE_TYPE_IN_USE` — switch it off instead |
| Switch off the last active type | 409 `LAST_ACTIVE_LEAVE_TYPE` |

The first is why the settings screen shows a usage count beside every type and simply does
not offer a delete button on one that has history. The second stops a settings screen from
breaking the module it configures: an empty catalogue is an empty picker, and nobody can
request leave at all.

Switching a type off is **not** retroactive. It disappears from the request form and from
both write paths' validation, and nothing else changes: the leave already taken under it
keeps its name, its days and its place in every balance. That is why `GET leave-types`
returns the switched-off rows too, flagged — the client needs them to name history, and the
picker is what filters on `is_active`.

### 38.5 Where it lives

Under **الدوام › أوقات الدوام والعطل**, as a third tab beside working weeks and holidays —
the same subject, the shape of the company's calendar. The tab is gated on `settings.view`
separately from the page's own `attendance.manage_schedules`: being allowed to set someone's
working week is not the same as deciding which kinds of leave the company recognises.

### 38.6 Key files

```
database/migrations/2026_09_22_140001_create_leave_types_table.php   (+ seeds the 5)
                    2026_09_22_140002_widen_leave_type_on_employee_leaves_table.php
app/Models/LeaveType.php                          (deductingKeys, scopes active/ordered)
app/Application/Services/LeaveTypeService.php     (the two guards, deriveKey)
app/Http/Controllers/Api/V1/LeaveTypeController.php
app/Http/Requests/StoreLeaveTypeRequest.php · UpdateLeaveTypeRequest.php
                   StoreEmployeeLeaveRequest.php · UpdateEmployeeLeaveRequest.php
app/Http/Resources/LeaveTypeResource.php
tests/Feature/LeaveTypeTest.php                   (14 tests)
Frontend/src/components/attendance/LeaveTypesTab.tsx
Frontend/src/hooks/useLeaveTypeLabel.ts
Frontend/src/api/leave-types.ts · types/leaves.ts
```

While moving the validation, `EmployeeLeaveController` finally got FormRequests. It was
calling `$request->validate()` inline with the type list spelled out twice — the last place
in the module doing so.

## 39. Tasks became lists, and check-out became ticking

A task is rarely one motion. "Ramadan campaign designs" is three posts, a cover
and a story, and a board that can only say *not started* or *done* about the
whole thing tells management nothing on the third day. So a task now carries
**lines** — `task_items`, a title and a tick each — and closing the day is
ticking them rather than writing a paragraph about them.

Two things were removed on the way, and one rule was added.

### 39.1 The client field is gone from the tasks screen

Tasks no longer ask which client the work is for. The column (`tasks.company_id`)
and the API field are still there and still accepted; nothing in the UI offers
them, and nothing displays them. That is deliberate — the field cost a decision
on every task and answered a question nobody was asking of the board — and it is
two lines of markup to put back if that turns out to be wrong.

The row's second line now carries **"2 من 4"** where the client used to be, which
is the one thing about progress a status badge cannot say.

### 39.2 A task follows its lines

```
first tick   →  todo        becomes in_progress  (started_at written)
last tick    →  open        becomes done         (completed_at written)
un-tick one  →  done        becomes in_progress  (completion cleared)
```

`TaskService::syncStatusWithItems()` does this after every add, tick and delete,
and it writes exactly the attributes the explicit move writes — both go through
`transitionAttributes()`, so a task finished by ticking the last box carries the
same timestamps as one finished by pressing the button.

Two exemptions, both deliberate:

- **A task with no lines is left entirely alone.** Its status is whatever
  somebody set, which is the right answer for one-motion work. Deleting the last
  line therefore does *not* read as "all lines done".
- **`cancelled` is never touched.** Work that was called off does not come back
  because somebody tidied a checkbox.

`completed_at` on a line is the done flag — there is no `is_done` column to
disagree with it — and ticking an already-ticked line does not move the time on
it, so two people pressing the same box cannot rewrite when it happened.

### 39.3 The one new rule: a line you were given is not a line you can drop

```
DELETE tasks/{task}/items/{item}   tasks.view_all|tasks.view_own
                                   + TaskController::mayRemoveItem()
```

An assignee may add lines (half of what anybody does in a day was not on the list
when the day started) and tick anything. They may delete only lines they wrote
themselves; `tasks.edit` deletes either.

Without that, an assignee can empty their own checklist and report the task
finished — which is precisely the thing the checklist exists to make visible.
`task_items.created_by` is what the rule reads, and `TaskItemResource` sends
`is_own` so the button and the API agree.

### 39.4 Check-out: the day accounts for itself, in either of two ways

`POST attendance/check-out` used to require `notes` every time. It now takes:

```
notes                 required only when completed_item_ids is empty (min:3)
completed_item_ids    the caller's own open lines, max 60
```

The objection to the old rule was never that the day should go unrecorded. It
was that a person who has just ticked four lines is being asked to type them
again, and what that teaches is the full stop. So a day closes on ticks, or on a
sentence, or on both — and on nothing is still refused.

`AttendanceService::checkOut()` composes the session's `notes` from the ticked
titles and whatever was typed beside them (`accountOfTheDay()`), server-side, so
the sentence management reads beside the hours is the same sentence however the
day was closed. The ticking happens **inside the check-out transaction**: if
closing the day fails — a stale open row from last week, say — the lines are not
left marked finished by a check-out that never happened.

`AttendanceService` now takes `TaskService` in its constructor. No cycle:
`TaskService` depends on `NotificationService` and `AttachmentService`, neither
of which knows about attendance.

**`GET tasks/checklist` is always the caller's own**, never a board, whatever
permissions they hold — a manager closing their own day is closing their own day,
and they read other people's work on the tasks screen. An account with no
employee profile gets an empty list rather than an error: it has no day to close.

`completeItemsFor()` puts the employee **in the query** rather than checking
afterwards, so an id belonging to somebody else's task is simply not found and a
hand-written request cannot close another person's work. A stale id is ignored
for the same reason it is not validated with `exists` — a day should not be
impossible to close because a manager deleted a line while the dialog was open.

### 39.5 Key files

```
database/migrations/2026_09_22_150001_create_task_items_table.php
app/Models/TaskItem.php                           (completed_at IS the flag; scopes pending/done)
app/Models/Task.php                               (items())
app/Application/Services/TaskService.php          (addItem, updateItem, deleteItem, itemOf,
                                                   checklistFor, completeItemsFor,
                                                   syncStatusWithItems, transitionAttributes)
app/Application/Services/AttendanceService.php    (checkOut takes User + ids; accountOfTheDay)
app/Http/Controllers/Api/V1/TaskController.php    (checklist, addItem, updateItem, deleteItem,
                                                   mayRemoveItem)
app/Http/Requests/StoreTaskItemRequest.php · UpdateTaskItemRequest.php
                   StoreTaskRequest.php           (items[] — a task is created with its lines)
                   CheckOutAttendanceRequest.php  (notes required only when nothing ticked)
app/Http/Resources/TaskItemResource.php · TaskResource.php  (items, items_total, items_done)
tests/Feature/TaskChecklistTest.php               (18 tests)
Frontend/src/pages/TasksPage.tsx                  (LinesField, TaskChecklist, ChecklistRow)
Frontend/src/components/attendance/CheckOutDialog.tsx
Frontend/src/components/tasks/TaskDigest.tsx      (progress replaces the client)
Frontend/src/api/tasks.ts · attendance.ts · types/tasks.ts · types/attendance.ts
Frontend/src/hooks/queries.ts                     (useTaskChecklist, useTaskItems)
```

A task can also be **created with its lines** in one request — `POST tasks` takes
`items: string[]`, blanks dropped. A manager who has to save the task and reopen
it to list what is in it will not list what is in it.

---

## 40. Work that has not been started has to find the person

A task assigned at nine in the evening was, until now, read by whoever happened
to open the tasks screen — which is nobody. The bell announced it once and the
notice was then marked read, so the *fact* that something was waiting survived
only in a list somebody had to go and look at.

Three places now carry it, all fed by one endpoint.

### 40.1 `GET tasks/pending` — always "mine", never the board

```
GET tasks/pending    permission:tasks.view_all|tasks.view_own
→ { count: int, tasks: TaskResource[] }   // the caller's own status = todo
```

`TaskService::notStartedFor()` resolves the employee from the caller
(`$user->employee?->id`) and **does not consult `AccessScope`**. This is the
same rule `tasks/checklist` follows (§39.4) and for the same reason: a manager
holding `tasks.view_all` cannot start somebody else's task, so counting the
company's untouched work on their badge would be a red dot they can never
clear. The permission on the route is only the door — it never widens the rows.

An account with no employee profile gets `{count: 0, tasks: []}`, not an error:
it is nobody's assignee, so nothing is waiting on it.

The count and the first few rows come back together because both readers are on
screen at the same moment — the sidebar wants the number, the landing strip
wants the titles — and two endpoints would poll twice for one fact. `tasks` is
capped at 5 and ordered the way every task list in this system is ordered
(`mostPressingFirst()`: overdue, then nearest due date, then priority).

### 40.2 Where it shows

| Surface | What it shows | Seen by |
|---|---|---|
| Sidebar, on **المهام** | the count, as the same red badge the leave entry uses (a plain dot on the collapsed rail) | anyone with a task permission |
| `/attendance`, above the check-in button | `<PendingTasksNotice>` — the count, the first 3 titles with priority and due date, "+N أخرى" | `team`, `marketing`, `sales` — everyone who starts their day there |
| `/` dashboard | **nothing** — the digest is untouched; see below | — |

The strip sits above the check-in card deliberately: the one moment everybody
reliably looks at this system is before they press **تسجيل دخول**, and `team`
and `marketing` have no dashboard to put it on (§19). It renders **nothing**
when nothing is waiting, so an ordinary morning still opens on the button.

**The dashboard carries none of this, by decision.** Three attempts to put it
there were each undone at the owner's request:

1. lifting `<TaskDigest>` above the KPI rows — they want figures, digest, team,
   in that order;
2. splitting the screen into **المهام** / **الإحصائيات** tabs;
3. a fifth **لم تبدأ** counter in the digest — which forced the counter row
   from `sm:grid-cols-4` to `sm:grid-cols-3 lg:grid-cols-5`, and so made the
   tiles visibly wider between 640px and 1024px. They noticed.

`<TaskDigest>` and `DashboardPage.tsx` are now **byte-for-byte what they were**
before this section's work. The lesson is in the third one: a tile added to a
fixed-column row is never only a tile — it is a re-flow of everything beside
it. The signal lives in the sidebar and on the attendance screen, which is
where it was asked for.

### 40.3 Key files

```
app/Application/Services/TaskService.php     (notStartedFor, mostPressingFirst)
app/Http/Controllers/Api/V1/TaskController.php  (pending)
routes/api.php                               (tasks/pending, above tasks/{task})
tests/Feature/TaskTest.php                   (3 tests: own only, own even for a
                                              manager, no employee profile)
Frontend/src/components/tasks/PendingTasksNotice.tsx   (new)
Frontend/src/components/tasks/TaskBadges.tsx  (TaskDueLabel, lifted out of TaskDigest)
Frontend/src/components/tasks/TaskDigest.tsx  (the "لم تبدأ" counter)
Frontend/src/components/layout/Sidebar.tsx    (NavItem.badgeHint — a badge now
                                               says what it counts)
Frontend/src/pages/AttendancePage.tsx          (the strip, above the check-in card)
Frontend/src/hooks/queries.ts                 (useMyPendingTasks — 60s poll,
                                               invalidated by every task write)
Frontend/src/api/tasks.ts · types/tasks.ts    (pending(), PendingTasks)
```

The badge clears the way the fact does: by starting the task. Nothing marks it
read, because it is not a notice — it is the state of the work.
