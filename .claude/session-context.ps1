# Emits the mandatory project rules into every Claude Code session (SessionStart hook).
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$file = Join-Path $PSScriptRoot 'session-context.md'
if (Test-Path $file) { Get-Content -Raw -Encoding UTF8 $file }
