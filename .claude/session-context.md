er## MANDATORY PROJECT RULES — PhoeniTech Management (نظام الإدارة العامة)

You are working in a Laravel 12 (PHP 8.2) API + React 19 SPA monorepo-ish project.

Before writing any code this session:
1. `CLAUDE.md` at the project root is binding. Follow it over generic defaults.
2. Touching auth / roles / permissions / users / login? Read `docs/access-control.md`
   first and update it in the same change.
3. Backend flow is always Controller -> app/Application/Services/XService -> Model.
   FormRequest for input, API Resource for output, `ApiResponse` for every JSON return,
   `BusinessRuleException` for domain errors. No business logic in controllers.
4. Every new route in `routes/api.php` goes inside the `['auth:sanctum','active']`
   group with its own `->middleware('permission:<module>.<action>')` and `->name(...)`.
5. Frontend strings go into BOTH `Frontend/src/i18n/ar.json` and `en.json`. Arabic is RTL.
   `Frontend/` is a separate git repo — never stage it from here.
6. Never edit `vendor/`, `node_modules/`, `vendor.zip`. Never print `.env` secrets.
   Never commit or push unless explicitly asked.
7. Reply to the user in Arabic. Code, comments and commits in English.

Project slash commands live in `.claude/commands/` — use them: /test, /endpoint,
/permission, /check, /brief.
