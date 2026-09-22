# `.claude/` — Claude Code configuration for this project

Everything here is committed so **every** session, on any machine, starts with the
same project rules.

| Path | Loaded when | Purpose |
|---|---|---|
| `../CLAUDE.md` | automatically, every session | Project memory: stack, layering, conventions, definition of done. The binding rulebook. |
| `session-context.md` | injected by the SessionStart hook | Short non-negotiable rules, repeated at session start so they survive long sessions and compaction. |
| `session-context.ps1` | run by the SessionStart hook | Prints `session-context.md` as UTF-8. Called via `-File` (no inline quoting) so it works under both Git Bash and cmd. |
| `settings.json` | every session | Permissions, denied paths (`vendor/`, `node_modules/`, `vendor.zip`), MCP, hooks. |
| `commands/*.md` | on demand, as `/name` | Project workflows: `/test`, `/check`, `/endpoint`, `/permission`, `/brief`. |
| `agents/*.md` | on demand | `laravel-reviewer` — reviews a diff against this project's own conventions. |

## Editing rules

- Change a convention → update `CLAUDE.md`. Change a *hard* rule → also update
  `session-context.md` (keep it short; it is injected into every session).
- Add a repeated workflow → add a file in `commands/` rather than re-explaining it.
- Keep `settings.json` valid JSON: `node -e "JSON.parse(require('fs').readFileSync('.claude/settings.json','utf8'))"`.
- Test the hook after editing it: `powershell -NoProfile -ExecutionPolicy Bypass -File .claude/session-context.ps1`
