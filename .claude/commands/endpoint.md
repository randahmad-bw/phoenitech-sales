---
description: Add a new API endpoint end-to-end following project conventions
argument-hint: "<method> <path> — what it does, e.g. POST contracts/{id}/renew"
allowed-tools: Bash, Read, Edit, Write, Grep, Glob
---

Add this endpoint: **$ARGUMENTS**

Follow the project's layering exactly (see CLAUDE.md §3). Before writing, read an
existing comparable module end-to-end (e.g. CompanyController + CompanyService +
StoreCompanyRequest + CompanyResource + its route + tests/Feature/CompanyTest.php)
and mirror its structure and naming.

Deliver, in this order:
1. The business logic in `app/Application/Services/<X>Service.php`
   (throw `BusinessRuleException` with an error code for domain rule violations).
2. A `FormRequest` in `app/Http/Requests/` if the endpoint takes input.
3. An API `Resource`/`Collection` in `app/Http/Resources/` for the output shape.
4. A thin controller action in `app/Http/Controllers/Api/V1/` returning via `ApiResponse`.
5. The route in `routes/api.php`, inside the `['auth:sanctum','active']` group, with
   `->middleware('permission:<module>.<action>')` and `->name(...)`.
6. If the permission is new: add it to `RolePermissionSeeder`, assign it to the right
   roles, and update `docs/access-control.md`.
7. A feature test in `tests/Feature/` covering success, validation failure, and a
   403 for a user lacking the permission.

Finish by running `./vendor/bin/pint` and the relevant test, and report the result.
