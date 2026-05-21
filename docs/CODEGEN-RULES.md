# Codegen Rules Index

Use this file as the entry point for implementation rules when a task touches `apps/laravel`.

Read only the sections that match the task at hand:

- [docs/rules/architecture-rules.md](docs/rules/architecture-rules.md)
  Use for controller/service/repository/model boundaries, feature folder placement, jobs/listeners/policies, transactions, and shared module structure.
- [docs/rules/coding-rules.md](docs/rules/coding-rules.md)
  Use for general coding style, naming, config handling, layering, database changes, and delivery expectations.
- [docs/rules/quality-rules.md](docs/rules/quality-rules.md)
  Use for method size, parameter count, typing, readability, data shaping, query efficiency, and maintainability heuristics.
- [docs/rules/frontend-rules.md](docs/rules/frontend-rules.md)
  Use for SPA visual design, layout systems, button/input/dropdown states, color usage, interaction behavior, accessibility, frontend UX consistency, and shared frontend architecture expectations.
- [docs/rules/docblock-rules.md](docs/rules/docblock-rules.md)
  Use for PHP docblocks plus JSDoc requirements in `resources/js`, including shared frontend contracts, components, and test helpers.
- [docs/rules/comment-rules.md](docs/rules/comment-rules.md)
  Use when adding or reviewing PHP comments, frontend JSDoc, and targeted inline comments.
- [docs/rules/testing-rules.md](docs/rules/testing-rules.md)
  Use when adding or updating tests, including folder structure and coverage expectations.
- [docs/rules/auth-provider-rules.md](docs/rules/auth-provider-rules.md)
  Use when changing authentication providers, OAuth configuration, verification policy, or provider-facing admin screens.
- [docs/rules/github-rules.md](docs/rules/github-rules.md)
  Use when creating branches, staging changes, writing commits, choosing commit message format, pushing, force-pushing, or preparing PR/issue follow-up notes.
- [docs/rules/verification-rules.md](docs/rules/verification-rules.md)
  Use before closing a Laravel task and before commit/push to run formatting, static analysis, tests, and local CI-style checks consistently.

Minimum reading path by task type:

- Laravel feature work:
  - `architecture-rules.md`
  - `coding-rules.md`
  - `quality-rules.md`
  - `frontend-rules.md` when the task touches SPA or SCSS
  - `docblock-rules.md`
  - `testing-rules.md`
  - `verification-rules.md`
- Frontend or SPA work:
  - `coding-rules.md`
  - `quality-rules.md`
  - `frontend-rules.md`
  - `docblock-rules.md`
  - `comment-rules.md`
  - `testing-rules.md`
  - `verification-rules.md`
- Auth provider or login flow work:
  - all files above
  - `auth-provider-rules.md`
- Refactor or cleanup work:
  - `architecture-rules.md`
  - `coding-rules.md`
  - `quality-rules.md`
  - `docblock-rules.md`
  - `comment-rules.md`
  - `github-rules.md` when the task includes branch/commit/push work
  - `verification-rules.md`
- Documentation-only work:
  - read only the relevant docs file being edited unless `AGENTS.md` says otherwise
  - also read `github-rules.md` when the task includes branch, commit, push, or PR note preparation
