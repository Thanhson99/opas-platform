# GitHub Rules

## Purpose

Use these rules whenever the task includes branch creation, staging, commits, pushes, force-pushes, or preparing PR and issue follow-up text.

## Branch Naming

- Start new work on a dedicated branch from the intended base branch.
- Prefer explicit prefixes:
  - `feature/...` for user-facing or product feature work
  - `fix/...` for bug fixes or regressions
  - `chore/...` for tooling, docs, maintenance, or cleanup
- Branch names should include the main topic or issue identifier when available.
- Good examples:
  - `feature/google-oauth-issue-64`
  - `fix/admin-sidebar-scroll-jitter`
  - `chore/verification-rules`

## Staging And Commit Scope

- Review `git status --short --branch` before staging or committing.
- Stage only the files that belong to the intended task unless the user explicitly wants a broader commit.
- Keep unrelated local changes out of a feature commit when possible.
- If the branch intentionally bundles multiple related changes, state that clearly in the handoff or PR note.

## Commit Messages

- Prefer concise conventional-style messages when they fit the repo:
  - `feat: ...`
  - `fix: ...`
  - `chore: ...`
  - `docs: ...`
- Commit messages should describe the user-facing or architectural outcome, not the mechanics of editing.
- If a commit closes or addresses a specific issue, include that in the PR or issue description even when the commit title stays short.

## Push And Force-Push

- Push feature work to a dedicated remote branch rather than directly to `main`.
- Force-push only when the user explicitly requests history rewriting or when you are replacing your own unpublished-or-review-branch history intentionally.
- When force-pushing, prefer a single cleaned-up commit only after verification has already passed on the exact content being pushed.
- After push or force-push, report:
  - branch name
  - commit hash
  - remote status or PR URL when available

## PR And Issue Follow-Up

- When preparing a PR or closing an issue, include:
  - short summary
  - verification commands that passed
  - any residual risk or notable follow-up
- If a task maps directly to an issue, provide a ready-to-paste title and description block when useful.
