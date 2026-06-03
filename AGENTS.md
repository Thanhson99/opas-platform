# Agent Rules

This file is intentionally short. Detailed rules live under `docs/ai/` so agents only load the context needed for the task.

## Required Reading

- Read [docs/ai/AGENTS.md](docs/ai/AGENTS.md) first to find the relevant AI rule folders.
- Read [docs/codegen-rules.md](docs/codegen-rules.md) before making application code changes.

When working on `apps/laravel`, use `docs/codegen-rules.md` as the Laravel rule index and read only the relevant rule files for the task:

- architecture rules when the task affects controllers, services, repositories, models, jobs, listeners, or module boundaries
- coding rules
- quality rules for method size, parameter count, naming, typing, and maintainability expectations
- frontend rules when the task affects SPA screens, SCSS, layout, interaction states, or visual design
- docblock rules
- comment rules
- testing rules
- auth provider rules when auth is affected
- github rules when creating branches, commits, pushes, or preparing PR/issue follow-up
- verification rules before closing the task and before commit/push verification

When a task touches the autonomous coding system:

- Use [docs/GLOBAL-ASSISTANT-CONTINUITY.md](docs/GLOBAL-ASSISTANT-CONTINUITY.md) as the repository-level continuity reference for fresh machines, source inspection, and operator-alignment context.

The global continuity file is not mandatory reading for every active session, but it must be checked when continuity, operator style, machine handoff, or "understand this repo like the other machine did" becomes relevant.
