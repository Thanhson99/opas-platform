# Verification Rules

## Required Checks Before Closing A Laravel Task

- Run `./vendor/bin/pint` on changed PHP files.
- Run `./vendor/bin/phpstan analyse --memory-limit=-1 --no-progress --configuration=phpstan.neon` on changed PHP paths or the narrowest relevant runtime scope.
- Run the narrowest relevant PHPUnit scope for the changed behavior.

## Failure Handling

- Do not ignore formatter failures without fixing them or documenting the blocker.
- Do not ignore static-analysis failures without fixing them or documenting the blocker.
- If a requested scope cannot be executed, report exactly what could not be run and why.
- Prefer verifying the smallest relevant scope first, then expand only if the change radius justifies it.
