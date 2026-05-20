# Verification Rules

## Required Checks Before Closing A Laravel Task

- Run `./vendor/bin/pint` on changed PHP files.
- Run `./vendor/bin/phpstan analyse --memory-limit=-1 --no-progress --configuration=phpstan.neon` on changed PHP paths or the narrowest relevant runtime scope.
- Run the narrowest relevant PHPUnit scope for the changed behavior.
- For frontend changes in `apps/laravel/resources/js` or Vite-related assets, run the closest CI-equivalent command before closing the task:
  - prefer `npm run ci` when the change touches shared frontend code, build output, routing, config, formatting-sensitive files, or multiple SPA areas
  - at minimum include `npm run check` when the task is intentionally narrower and you are explicitly not validating the bundle

## Pre-Commit And Pre-Push Workflow

- Before creating a commit, review `git status` and confirm the staged scope matches the intended issue or task.
- For `apps/laravel` work, prefer this local verification order:
  - `npm ci` when frontend dependencies or the local JS toolchain may be out of sync
  - `./vendor/bin/pint` on changed PHP files
  - `./vendor/bin/phpstan analyse --memory-limit=-1 --no-progress --configuration=phpstan.neon ...`
  - `php artisan test ...` for the narrowest relevant backend scope
  - `npm run check` for SPA changes so both `eslint` and `prettier --check` run before push
  - `npm run build` when the frontend bundle, Vite config, or shared UI shell changed
  - `npm test -- --run ...` for the narrowest relevant frontend/Vitest scope when React components or contexts changed
  - `npm run ci` instead of separate frontend commands when you want parity with the CI pipeline or when the diff touches shared frontend code across multiple files
- Before pushing a branch, re-run the narrowest set that proves the final staged diff is still green after the last edits.
- If a task is large or cross-cutting, prefer collecting the exact commands and results in the PR or handoff note.
- If CI uses a named wrapper command such as `npm run ci`, prefer documenting and running that exact command in addition to any narrower local checks that helped during development.

## Suggested Local Checklist

- `git status --short --branch`
- `cd apps/laravel`
- `npm ci`
- `./vendor/bin/pint ...`
- `./vendor/bin/phpstan analyse --memory-limit=-1 --no-progress --configuration=phpstan.neon ...`
- `php artisan test ...`
- `npm run check`
- `npm run build`
- `npm test -- --run ...`
- `npm run ci`
- `git status --short`

## Failure Handling

- Do not ignore formatter failures without fixing them or documenting the blocker.
- Do not ignore static-analysis failures without fixing them or documenting the blocker.
- Do not treat `eslint` as sufficient when the repository also enforces `prettier --check` in CI.
- If a requested scope cannot be executed, report exactly what could not be run and why.
- Prefer verifying the smallest relevant scope first, then expand only if the change radius justifies it.
