# AI Rule System Index

This file is intentionally short. It tells any AI agent where the focused rules live.

## Core Entry

- Read [core/README.md](core/README.md) for global behavior and core principles.
- Read only the focused folder needed for the task.
- Do not load every rule file by default.

## Rule Folders

- [architecture/](architecture/README.md): monorepo boundaries, dependency flow, ownership.
- [frontend/](frontend/README.md): React rules, layout system, UI system, state management.
- [backend/](backend/README.md): Laravel rules and API contract.
- [quality/](quality/README.md): testing strategy and anti-patterns.
- [maintenance/](maintenance/README.md): self-maintaining checks, violation detection, refactor triggers, quality gate.
- [review/](review/README.md): AI code reviewer CI system, severity model, report format, quality gate.
- [workflow/](workflow/README.md): development process and existing repository rule usage.

## Existing Repo Rules

- Read [../codegen-rules.md](../codegen-rules.md) before application code changes.
- For `apps/laravel`, use `docs/codegen-rules.md` to select relevant `docs/rules/*` files.

## Rule Priority

- More specific task rules override general rules.
- Hard enforcement rules are blocker-level when they apply.
- Existing repository patterns override generic examples unless they violate a hard rule.
- Do not expand scope to fix unrelated pre-existing violations unless the user asks or the violation blocks the requested change.
