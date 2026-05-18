# Codegen Rules

## Purpose

Use this file as the default engineering rulebook for changes in this repository.

When a task touches `apps/laravel`, read this document first and follow it unless the user explicitly asks for a different tradeoff.

## Config And Secrets

- Never hardcode secrets, API keys, client secrets, passwords, or environment-specific credentials in PHP, JS, migrations, seeders, or tests.
- Put environment-specific values in `apps/laravel/.env` and document required keys in `apps/laravel/.env.example`.
- Put runtime access to env values behind Laravel config files such as `config/opas.php`, `config/services.php`, `config/queue.php`, or `config/mail.php`.
- Application code should read from `config()` instead of calling `env()` directly outside config files.
- Do not hardcode provider URLs, callback base URLs, API base URLs, or OAuth endpoints in app code. Put them in config and source them from env when they may vary by environment or provider version.
- DB-stored provider credentials belong in the database and must be encrypted at rest when sensitive.
- Frontend code must never receive secrets. Expose only safe metadata.

## Laravel Layering

- Keep controllers thin.
- Controllers should validate input, call services, and return resources or responses.
- Business logic belongs in services.
- Database query orchestration belongs in repositories.
- Eloquent models should stay small and focused on relationships, casts, accessors, and narrowly scoped helpers.
- If a feature touches multiple aggregates or has branching rules, create or extend a service instead of growing controller logic.
- If a service starts accumulating raw query details, move those queries into a repository.

## Database Rules

- Every schema change must go through a migration.
- Do not bury schema creation or schema mutation in services or controllers.
- When a feature adds persistence behavior, decide whether it belongs in an existing repository or needs a new repository.
- Sensitive tokens, secrets, and long-lived credentials must be encrypted using Laravel casts or equivalent backend protection.
- Prefer explicit column names and indexes for lookup-heavy auth tables.

## Auth Provider Rules

- Provider lists must resolve dynamically from backend state, not from hardcoded frontend arrays.
- Provider readiness checks belong in auth provider drivers or services, not in controllers.
- Provider-specific OAuth logic should live in provider drivers or dedicated auth services.
- Email/password flow, OAuth flow, verification flow, and provider config flow should stay separated by responsibility.
- Callback URLs exposed to admins or frontend should be backend-generated.
- Secret provider config must not be returned in plaintext responses.

## Function And Class Structure

- If a function becomes long or handles more than one concern, split it into smaller private methods.
- Prefer small methods with descriptive names over a single large method.
- Services should expose a small public API and hide branching logic in private methods.
- When a class grows by feature area, split by responsibility rather than adding unrelated helper methods.

## Docblock Rules

- Every public and protected PHP method in `app/` must have a docblock with a one-line summary.
- Private PHP methods should also have a docblock when they contain branching, data shaping, persistence rules, auth rules, or non-trivial formatting logic.
- Every documented method must include `@return`, even when the native PHP return type is obvious.
- Every documented method with parameters must include `@param` for each parameter.
- Array-shaped payloads must declare their array shape in `@param` or `@return` whenever practical.
- Keep docblocks concise and factual.
- Avoid docblocks that restate the function name without adding meaning.

## Frontend Rules

- Frontend should consume backend contracts rather than reconstruct backend rules locally.
- Keep API access inside shared API helpers or feature context layers.
- Do not duplicate auth or readiness logic in multiple screens if a shared hook or context can own it.
- Never store backend secrets or privileged config in SPA state.

## Testing Rules

- Add or update feature tests for user-facing behavior changes.
- Add or update unit tests for service-level branching or validation rules.
- For auth changes, cover success path, denial path, and invalid configuration path where relevant.

## Static Analysis And Formatting

- Before closing a Laravel code task, run `./vendor/bin/pint` on changed PHP files.
- Before closing a Laravel code task, run `./vendor/bin/phpstan analyse --memory-limit=-1 --no-progress --configuration=phpstan.neon` on changed PHP paths or the narrowest relevant scope.
- Do not ignore formatter or static-analysis failures without documenting the reason to the user.

## Delivery Checklist

- Required env keys are present in `apps/laravel/.env.example`.
- Runtime config reads from `config()`, not direct `env()` calls in app code.
- Controllers remain thin.
- Database logic is in repositories or models only where appropriate.
- Sensitive values are not exposed to frontend or logs.
- New and modified PHP methods follow the docblock rules above, including explicit `@param` and `@return`.
- Tests cover the new behavior.
- `pint` has been run for changed PHP files.
- `phpstan` has been run for the changed Laravel scope.
