---
description: Add or change a permission/role consistently across backend, frontend and docs
argument-hint: "<permission or role change, e.g. add attendance.approve for hr_manager>"
allowed-tools: Bash, Read, Edit, Write, Grep, Glob
---

Access-control change requested: **$ARGUMENTS**

`docs/access-control.md` is the source of truth. Read it first, in full.

Apply the change in all four places — an incomplete change is a bug:
1. `database/seeders/RolePermissionSeeder.php` — the permission itself and every role
   that should hold it.
2. `routes/api.php` — the `permission:` middleware on the affected routes.
3. Frontend — `usePermissions` / `<Can>` usage so the UI matches the API, plus any
   new i18n strings in **both** `ar.json` and `en.json`.
4. `docs/access-control.md` — tables, permission count, and the "Last updated" date.

Then add or update a test in `tests/Feature/AuthorizationTest.php` (or
`RoleManagementTest.php`) proving a user without the permission gets 403 and a user
with it succeeds. Run `composer test --filter=Authorization` and report.

Remember: `super_admin` bypasses all checks — never special-case it in route or UI logic.
