---
description: Full pre-commit check — pint, backend tests, frontend build and lint
allowed-tools: Bash, Read, Edit, Grep, Glob
---

Run the project's definition-of-done checks in order and report a short table of results.

1. `./vendor/bin/pint` (formatting; report which files it changed)
2. `composer test`
3. `cd Frontend && npm run build` (runs `tsc -b`)
4. `cd Frontend && npm run lint`

Rules:
- Run every step even if an earlier one fails, then report all results together.
- Do not commit or push anything.
- If a step fails, show the actual error output — do not summarize it away.
- Finish with a clear verdict: ready to commit, or the exact list of blockers.
