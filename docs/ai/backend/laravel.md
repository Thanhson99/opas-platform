# Laravel Rules

## Architecture

```text
Controller -> Service -> Repository -> Model
```

## Controller Rules

- Controllers stay thin.
- Controllers validate input, call services, and return resources or responses.
- No business logic in controllers.
- No SQL or database orchestration in controllers.
- No plaintext secrets, privileged config, or debug-only fields in responses.

## Service Rules

- Business logic belongs in services.
- Services own workflows, branching rules, and cross-field domain validation.
- Services use repositories for reusable query and persistence work.
- Use transactions when multiple mutations must stay consistent.

## Repository Rules

- Repositories own query intent, ordering, lookup constraints, and reusable persistence behavior.
- Do not put unrelated business branching into repositories.
- Keep database access deterministic and testable.

## Model Rules

- Models stay focused on relationships, casts, accessors, and narrow helpers.
- Sensitive values must be encrypted at rest where appropriate.
- Schema changes require migrations.

## Config Rules

- Runtime configuration reads from Laravel config.
- Do not call `env()` directly outside config files.
