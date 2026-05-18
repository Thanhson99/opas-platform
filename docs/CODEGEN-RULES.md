# Codegen Rules Index

Use this file as the entry point for implementation rules when a task touches `apps/laravel`.

Read only the sections that match the task at hand:

- [docs/rules/coding-rules.md](docs/rules/coding-rules.md)
  Use for general coding style, naming, config handling, layering, database changes, and delivery expectations.
- [docs/rules/docblock-rules.md](docs/rules/docblock-rules.md)
  Use for PHP docblock requirements in `app/`.
- [docs/rules/comment-rules.md](docs/rules/comment-rules.md)
  Use when adding or reviewing code comments.
- [docs/rules/testing-rules.md](docs/rules/testing-rules.md)
  Use when adding or updating tests, including folder structure and coverage expectations.
- [docs/rules/auth-provider-rules.md](docs/rules/auth-provider-rules.md)
  Use when changing authentication providers, OAuth configuration, verification policy, or provider-facing admin screens.
- [docs/rules/verification-rules.md](docs/rules/verification-rules.md)
  Use before closing a Laravel task to run formatting and static analysis consistently.

Minimum reading path by task type:

- Laravel feature work:
  - `coding-rules.md`
  - `docblock-rules.md`
  - `testing-rules.md`
  - `verification-rules.md`
- Auth provider or login flow work:
  - all files above
  - `auth-provider-rules.md`
- Documentation-only work:
  - read only the relevant docs file being edited unless `AGENTS.md` says otherwise
