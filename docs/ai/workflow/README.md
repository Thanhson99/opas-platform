# Development Workflow

1. Understand architecture.
2. Read the relevant AI rule folder.
3. If the task changes code or reviews existing code, read `docs/ai/maintenance/README.md`.
4. Read `docs/codegen-rules.md` before application code changes.
5. Check existing components, hooks, services, layouts, API helpers, and tests.
6. Reuse before creating new abstractions.
7. Identify the owning layer.
8. Implement the feature or fix.
9. Validate layout and responsive behavior when UI changes.
10. Ensure API contract consistency when API changes.
11. Refactor if duplication is introduced.
12. Run narrow verification.
13. Report what changed, what was verified, and what could not be verified.

## Existing Repository Workflow

When working on `apps/laravel`, use `docs/codegen-rules.md` and read only relevant rule files:

- architecture rules
- coding rules
- quality rules
- frontend rules
- docblock rules
- comment rules
- testing rules
- auth provider rules when auth is affected
- github rules for branch/commit/push/PR work
- verification rules before closing

When a task touches autonomous coding behavior, read `docs/GLOBAL-ASSISTANT-CONTINUITY.md`.

When a task prepares PR review automation, CI review prompts, or merge quality gates, read `docs/ai/review/README.md`.
