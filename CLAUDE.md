# CLAUDE.md — PhoeniTech Management (نظام الإدارة العامة)

> Project memory. Loaded automatically at the start of **every** session.
> Rules here are binding. If a rule conflicts with a generic default, this file wins.
> Keep it updated whenever architecture, stack, or conventions change.

---

## 1. What this project is

General company-management system for PhoeniTech (renamed from "Sales" — do **not**
call it a sales/CRM system): employees, companies, contacts, contracts, payments,
subscriptions & renewals, social media, attachments, reports, audit log, and an
upcoming attendance/HR module.

| Layer | Stack |
|---|---|
| Backend | Laravel 12, PHP 8.2 (`^8.2` — **not** 8.3), Sanctum 4 token auth, REST at `/api/v1` |
| Authorization | `spatie/laravel-permission` **^6.25** (v8 requires PHP 8.3 — do not upgrade), guard `web` |
| Query/Export | `spatie/laravel-query-builder`, `maatwebsite/excel`, `barryvdh/laravel-dompdf` |
| DB | dev: MySQL/MariaDB via XAMPP (`DB_CONNECTION=mysql`, db `phoenitech_sales`) · prod: MariaDB 11.4 · tests: in-memory SQLite (set in `phpunit.xml`) |
| Frontend | React 19 + TypeScript + Vite, Tailwind 4, TanStack Query, Zustand, react-router, react-hook-form + Zod, i18next (ar/en, RTL), Recharts, oxlint |

## 2. Repo layout

```
app/Http/Controllers/Api/V1/   thin controllers — one per resource
app/Http/Requests/             FormRequest validation (StoreXRequest / UpdateXRequest)
app/Http/Resources/            XResource + XCollection output shaping
app/Http/Responses/            ApiResponse — the ONLY way to return JSON
app/Http/Middleware/           CheckPermission ('permission'), EnsureUserIsActive ('active')
app/Application/Services/      all business logic lives here
app/Infrastructure/Repositories/  base repository contracts (rarely touched)
app/Models/                    Eloquent models
app/Domain/                    EMPTY legacy folders — do not add code here
routes/api.php                 every endpoint, each with its permission middleware
bootstrap/app.php              routing prefix, middleware aliases, exception → ApiResponse mapping
tests/Feature/                 PHPUnit feature tests (one per module)
docs/access-control.md         SOURCE OF TRUTH for auth/roles/permissions
Frontend/                      React SPA — SEPARATE GIT REPO, gitignored here
AiMind/                        planning/task notes — gitignored, not runtime code
```

## 3. Backend conventions (non-negotiable)

1. **Flow:** `Controller → Application\Services\XService → Model`. Controllers never
   contain business logic, queries, or conditionals beyond mapping a service result
   to a response. Inject the service via constructor property promotion.
2. **Responses:** every JSON response goes through `App\Http\Responses\ApiResponse`
   (`success`, `created`, `paginated`, `notFound`, `conflict`, `unauthorized`,
   `validationError`). Never `response()->json(...)` directly in a controller.
3. **Validation:** always a FormRequest. Never `$request->validate()` inline.
4. **Output:** always an API Resource / ResourceCollection. Never return a model or
   array straight to the client.
5. **Domain errors:** throw `App\Exceptions\BusinessRuleException` with an error code —
   `bootstrap/app.php` already maps it to a 409 envelope. Don't catch-and-format manually.
6. **Every new route** in `routes/api.php` must sit inside the
   `['auth:sanctum', 'active']` group and declare its own
   `->middleware('permission:<module>.<action>')` plus a `->name(...)`.
7. **New permission = 4 edits:** the seeder (`RolePermissionSeeder`), the route, the
   frontend `usePermissions`/`<Can>` usage, and `docs/access-control.md`.
8. Style: run `./vendor/bin/pint` before finishing backend work.

## 4. Auth & RBAC

- `docs/access-control.md` is authoritative — **read it before touching auth, roles,
  permissions, users, or login**, and **update it in the same change**.
- 6 roles, 72 permissions. `super_admin` bypasses every check.
- **A role says what an account may _reach_; a department says what the person _does_.**
  Never add a role for a department that needs no different access.
  Roles: `super_admin` · `general_manager` (all operational, **no** `users.*`/`roles.*`) ·
  `sales_manager` · `sales` · `marketing` · `team` (design/dev/photography/video —
  attendance + own profile + own leave, **nothing commercial**).
- Departments are `Employee::DEPARTMENTS` (validated): sales, marketing, design, dev,
  photography, video, management. Frontend mirror: `src/constants/departments.ts`;
  labels only in `employee.department_<id>`.
- Renamed in phase 20: `employee`→`sales`, `staff`→`team`, `manager`→`general_manager`;
  `admin` merged into `general_manager`, `hr` deleted. See `docs/access-control.md` §32.
- Super admin login: `info@phoenitech.sy`. All seeded accounts use `@phoenitech.sy`.
- Seed password comes from `env('SEED_PASSWORD')`.
- Login is throttled per email+IP; inactive accounts are rejected by the `active` middleware.

## 5. Frontend conventions (`Frontend/`)

- `Frontend/docs/design-system.md` is authoritative for the **look** — tokens, the
  type scale, the component inventory, and the recipe for a new screen. **Read it
  before building or restyling any screen**, and update it in the same change if
  you touch `src/index.css` or `src/components/ui/`.
- Separate git repository, intentionally gitignored in this repo. Never `git add` it here.
- HTTP calls only through `src/api/*.ts` modules using the shared `src/lib/axios.ts` client.
- Server state via TanStack Query in `src/hooks/queries.ts`; UI/auth state via Zustand `src/store`.
- Permission gating via `src/hooks/usePermissions.ts` and the `<Can>` component — never
  hide UI by hard-coding a role name.
- **i18n is mandatory:** any user-facing string must be added to **both**
  `src/i18n/ar.json` and `src/i18n/en.json`. No literal strings in components.
  Arabic is RTL — check layout in both directions.
- **Dates, times and figures are English in both languages** — always through
  `src/utils` (`formatDate`, `formatDateTime`, `formatNumber`, `formatCurrency`).
  Never read `i18n.language` to pick a date locale. See design-system.md §8.
- **Every new route is `lazy()` in `src/App.tsx`.** A static page import puts the
  whole screen back on the critical path for the login form.
- No gradients, `backdrop-filter`, glows, or font weights above `font-semibold`.
  No `uppercase` / `tracking-*` (both are meaningless or harmful in Arabic).
- Verify with `npm run build` (runs `tsc -b`) and `npm run lint` (oxlint).

### Page layout (one shape for every screen)

> Summarised here; `Frontend/docs/design-system.md` is the full reference.

Every route renders `<Page icon title subtitle actions>` from `src/components/ui/Page.tsx`
and nothing else at the top level. Order inside is always: header → toolbar → content.

- **Padding and max width belong to `AppShell`, never to a page.** A page that adds its
  own `p-6` / `max-w-*` stacks on the shell's and pushes its title down the screen.
- Filters go in `<Toolbar>` — one compact row, not a titled `SectionCard`.
- A page's main data table goes in `<TableCard>` (or `<Table>`), which pins the column
  headings and scrolls inside itself. Pass `reserve` when the page puts extra rows
  (counters, a warning strip) between the header and the table.
- Counters use `<StatCard>`; above a table use `compact`. In-page view switching uses
  `<Tabs>`. Small figures inside a card or modal use `<MiniStat>`.
- The section trail in the top bar is derived from the route via
  `src/constants/sections.ts` — add a route there, don't print it on the page.
- No page invents its own `<h1>`, gradient banner or heading scale.

## 6. Commands

```bash
composer test                  # config:clear + artisan test
php artisan test --filter=X    # single test
./vendor/bin/pint              # PHP formatting
php artisan migrate --seed
cd Frontend && npm run dev|build|lint
```

## 7. Hard rules

- Never commit or print secrets from `.env`. `.env.example` stays in sync with new keys.
- Never edit `vendor/`, `node_modules/`, `vendor.zip`, or `.phpunit.result.cache`.
- Do not upgrade `spatie/laravel-permission` past 6.x (PHP 8.2 constraint).
- Do not commit or push unless explicitly asked.
- Code, comments, commit messages, and identifiers: **English**.
  Replies to the user and user-facing UI copy: **Arabic** (with English where the user uses it).

## 8. Definition of done

- [ ] Business logic in a service, controller thin
- [ ] FormRequest + API Resource + `ApiResponse` used
- [ ] Route carries `permission:` middleware and a name
- [ ] Feature test added/updated in `tests/Feature/` and `composer test` passes
- [ ] `./vendor/bin/pint` clean
- [ ] Frontend: both `ar.json` and `en.json` updated, `npm run build` passes
- [ ] Frontend UI: built from `Frontend/docs/design-system.md` (tokens, type scale,
      existing components); checked in dark **and** light, Arabic **and** English
- [ ] `docs/access-control.md` updated if auth/RBAC changed
- [ ] `Frontend/docs/design-system.md` updated if tokens or shared components changed
