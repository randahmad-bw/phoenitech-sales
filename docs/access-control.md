# Authentication, Roles & Permissions — Reference

> نظام الإدارة العامة (PhoeniTech Management). هذا المرجع يوثّق نظام تسجيل الدخول
> والأدوار والصلاحيات بالكامل. حدّثه مع كل تغيير على هذه الطبقة.
>
> This document is the source of truth for how auth & access control work in this
> project. Keep it updated whenever the auth/RBAC layer changes.

Last updated: 2026-09-19.

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

---

## 2. Login accounts (seeded)

Created by `UserSeeder`; roles assigned by `RolePermissionSeeder`. All emails use `@phoenitech.sy`.
Seed password comes from `env('SEED_PASSWORD', 'password')` — **override it in production**.

| Email (login) | Name | Role |
|---|---|---|
| `info@phoenitech.sy` | الإدارة | **super_admin** |
| `antoine.haddad@phoenitech.sy` | Antoine Haddad | admin |
| `admin@phoenitech.sy` | System Admin | admin |
| `rand.ahmad@phoenitech.sy` | Rand Ahmad (Operations Manager) | manager |
| `sara@phoenitech.sy` | سارة حسون | employee |
| `michael@phoenitech.sy` | مايكل حبيب | employee |

Login accepts **email OR username** in the `email` field. Disabled accounts (`is_active = false`) cannot log in.

`users` table auth columns (added by migration `..._add_auth_fields_to_users_table`):
`username` (unique, nullable), `is_active` (default true), `last_login_at`, `last_login_ip`, `must_change_password`.

---

## 3. Roles

Five baseline roles (editable from the dashboard except `super_admin`):

- **super_admin** — bypasses every check via `Gate::before` (AppServiceProvider). Protected: cannot be renamed or deleted. Cannot delete/disable/demote the *last* active super_admin.
- **admin** — all permissions.
- **manager** — operational oversight: employees (view), companies, contracts, payments (view), subscriptions (view/renew), social media, leave/overtime approval, reports, weekly reports (view all). No user/role management.
- **hr** — employees (incl. salary), leave, overtime, attendance, reports, weekly reports (view all).
- **employee** — self-service only (`*.view_own`, create own leave/overtime/weekly report/attendance).

## 4. Permission catalog (64 permissions)

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
settings     view edit
audit        view
attendance   view_all view_own create edit delete approve   (reserved for the HR module)
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
- Dashboard (`/dashboard`) is open to any authenticated user and exposes company financials — needs a per-role scoping decision.
- Phase 6: Frontend access control — route guards, `<Can>` component, sidebar filtering, 403 page, change-password UI, Users/Roles admin screens, the audit-trail screen, force `must_change_password`.
- Give login accounts to profile-only employees (design/photography) before the attendance module.

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
