# Coding Rules

## Purpose

Use these rules as the default engineering baseline for Laravel application code.

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
- Follow `architecture-rules.md` for responsibility boundaries before introducing new files or patterns.

## Naming And Readability Rules

- Prefer explicit names over short names unless the scope is trivially small.
- Method names should describe behavior, not implementation steps.
- Variable names should reflect domain meaning, not temporary mechanics such as `data`, `result`, or `item`, unless the scope is tiny and obvious.
- Avoid boolean names that hide meaning. Prefer `isReady`, `hasStoredSecret`, or `requiresVerification` over generic names like `flag` or `status`.
- Keep nesting shallow. Prefer guard clauses and early returns over deeply nested conditionals.
- If a method needs comments to explain each branch, the method likely needs to be split.
- Follow `quality-rules.md` heuristics for method length, parameter count, branch count, and extraction triggers.

## Validation And Error Handling Rules

- Validate external input at the boundary layer closest to entry, usually a request object or dedicated validator.
- Treat request validation and business-rule validation as separate concerns.
- Use request rules for shape and primitive constraints.
- Use services for cross-field, persistence-aware, or domain-specific validation.
- Return safe, stable error responses. Do not leak secrets, stack traces, or internal infrastructure details.
- Prefer explicit failure paths over silent fallback unless the fallback is an intentional product rule.

## Service Rules

- Services should orchestrate business rules, not behave like generic helper bags.
- A service should expose a small public API aligned to a domain capability.
- Hide branch-heavy logic in private methods with names that express the rule being applied.
- Do not let services read directly from superglobals, raw requests, or frontend-specific state.
- Use transactions explicitly when a service updates multiple records that must stay consistent.

## Repository Rules

- Repositories should own query intent, ordering rules, and lookup constraints that matter to the domain.
- Repositories should not absorb unrelated business branching.
- If a query is reused across services or controllers, prefer moving it to a repository method with a stable name.
- If a repository method returns a refreshed model after persistence, make that behavior explicit and consistent.
- Repositories should centralize eager-loading and deterministic ordering rules when those rules are part of the feature contract.

## Resource And Response Rules

- Resources should shape transport contracts, not decide business rules.
- Keep secret filtering, metadata shaping, and field exposure explicit in resources.
- Do not return plaintext secrets, privileged config, or debug-only fields from resources.
- Keep API contracts predictable. Avoid returning the same concept under multiple names.

## Database Rules

- Every schema change must go through a migration.
- Do not bury schema creation or schema mutation in services or controllers.
- When a feature adds persistence behavior, decide whether it belongs in an existing repository or needs a new repository.
- Sensitive tokens, secrets, and long-lived credentials must be encrypted using Laravel casts or equivalent backend protection.
- Prefer explicit column names and indexes for lookup-heavy auth tables.
- Declare model casts for JSON, encrypted, enum, and datetime-backed fields when the type matters to the domain.

## Function And Class Structure

- If a function becomes long or handles more than one concern, split it into smaller private methods.
- Prefer small methods with descriptive names over a single large method.
- Services should expose a small public API and hide branching logic in private methods.
- When a class grows by feature area, split by responsibility rather than adding unrelated helper methods.
- Prefer cohesive feature-oriented class design over generic utility dumping.

## Frontend Rules

- Frontend should consume backend contracts rather than reconstruct backend rules locally.
- Keep API access inside shared API helpers or feature context layers.
- Do not duplicate auth or readiness logic in multiple screens if a shared hook or context can own it.
- Never store backend secrets or privileged config in SPA state.

## Delivery Checklist

- Architecture boundaries match the owning layer.
- Required env keys are present in `apps/laravel/.env.example`.
- Runtime config reads from `config()`, not direct `env()` calls in app code.
- Controllers remain thin.
- Database logic is in repositories or models only where appropriate.
- Sensitive values are not exposed to frontend or logs.
- Model casts, relationships, and persistence-sensitive types are explicit.
- Long methods and oversized parameter lists were reviewed against `quality-rules.md`.
