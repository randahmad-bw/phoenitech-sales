---
description: Orient in this project — stack, structure, RBAC state and current git status
allowed-tools: Bash, Read, Grep, Glob
---

Give a concise orientation briefing for this project. Read, don't guess:

- `CLAUDE.md` (conventions), `docs/access-control.md` (auth/RBAC state and phase table)
- `git status` and `git log --oneline -10`
- `routes/api.php` (which modules exist), `app/Application/Services/` (which services exist)
- `tests/Feature/` (what is covered)

Report in Arabic, in under 25 lines:
1. What the system does and its current phase/status
2. Modules that exist vs. modules mentioned as planned/pending
3. Uncommitted work in the tree right now
4. Anything that looks inconsistent or unfinished and worth raising

Do not modify any file.
