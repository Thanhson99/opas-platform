# Claude Coding Instructions

This file is intentionally short. Detailed rules live under `docs/ai/`.

## Claude Workflow

1. Read `docs/ai/AGENTS.md`.
2. Read only the focused rule folder needed for the task.
3. Read `docs/codegen-rules.md` before application code changes.
4. Inspect existing code before editing.
5. Identify the owning layer.
6. Plan the smallest safe change.
7. Implement within existing architecture.
8. Run narrow verification and report exact results.

## Rule Locations

- Global behavior: `docs/ai/core/README.md`
- Architecture: `docs/ai/architecture/README.md`
- Frontend/layout/UI/state: `docs/ai/frontend/`
- Backend/API: `docs/ai/backend/`
- Testing/anti-patterns: `docs/ai/quality/`
- Workflow: `docs/ai/workflow/README.md`
