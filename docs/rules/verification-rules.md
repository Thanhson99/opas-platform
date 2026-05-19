# Verification Rules

## Required Checks Before Closing A Laravel Task

- Run `./vendor/bin/pint` on changed PHP files.
- Run `./vendor/bin/phpstan analyse --memory-limit=-1 --no-progress --configuration=phpstan.neon` on changed PHP paths or the narrowest relevant runtime scope.
- Run the narrowest relevant PHPUnit scope for the changed behavior.

## Pre-Commit And Pre-Push Workflow

- Before creating a commit, review `git status` and confirm the staged scope matches the intended issue or task.
- For `apps/laravel` work, prefer this local verification order:
  - `npm ci` when frontend dependencies or the local JS toolchain may be out of sync
  - `./vendor/bin/pint` on changed PHP files
  - `./vendor/bin/phpstan analyse --memory-limit=-1 --no-progress --configuration=phpstan.neon ...`
  - `php artisan test ...` for the narrowest relevant backend scope
  - `npm run lint` for SPA changes
  - `npm run build` when the frontend bundle, Vite config, or shared UI shell changed
  - `npm test -- --run ...` for the narrowest relevant frontend/Vitest scope when React components or contexts changed
- Before pushing a branch, re-run the narrowest set that proves the final staged diff is still green after the last edits.
- If a task is large or cross-cutting, prefer collecting the exact commands and results in the PR or handoff note.

## Suggested Local Checklist

- `git status --short --branch`
- `cd apps/laravel`
- `npm ci`
- `./vendor/bin/pint ...`
- `./vendor/bin/phpstan analyse --memory-limit=-1 --no-progress --configuration=phpstan.neon ...`
- `php artisan test ...`
- `npm run lint`
- `npm run build`
- `npm test -- --run ...`
- `git status --short`

## Failure Handling

- Do not ignore formatter failures without fixing them or documenting the blocker.
- Do not ignore static-analysis failures without fixing them or documenting the blocker.
- If a requested scope cannot be executed, report exactly what could not be run and why.
- Prefer verifying the smallest relevant scope first, then expand only if the change radius justifies it.
