---
description: Run the backend test suite (optionally filtered) and report failures
argument-hint: "[filter, e.g. AuthorizationTest or a test method name]"
allowed-tools: Bash, Read, Edit, Grep, Glob
---

Run the PHPUnit feature tests for this Laravel project.

- With an argument: `php artisan test --filter=$ARGUMENTS`
- Without one: `composer test` (clears config, then runs the full suite)

Then:
1. Report pass/fail counts plainly. If anything fails, paste the relevant failure output.
2. For each failure, read the test and the code under test before proposing a fix.
3. Never "fix" a test by weakening its assertions to make it pass — fix the code, or
   explain why the expectation itself is wrong.
