# AI Code Reviewer CI System

This folder defines the AI reviewer behavior for pull requests, commits, and merge requests. The reviewer acts as an automated senior engineer gate for React + Laravel changes.

## Core Idea

AI Code Reviewer CI System is an automated senior engineer gate.

It reviews:

- architecture compliance
- code quality
- duplication
- UI/UX consistency
- Laravel backend structure
- API contract consistency
- performance risk
- security risk
- refactor opportunities

## CI Pipeline Flow

```text
Git Push / Pull Request
  -> Diff Analyzer
  -> AI Context Builder
  -> Rule Engine
  -> AI Reviewer Engine
  -> Report Generator
  -> GitHub/GitLab Comment Bot
  -> Optional Merge Block
```

## Core Components

Diff Analyzer:

- detects changed files
- classifies change type:
  - UI change
  - API change
  - backend logic change
  - layout change
  - state change
  - test-only change
  - documentation-only change

Context Builder:

- loads related components
- loads related hooks and services
- loads related Laravel modules
- loads existing patterns in repo
- loads focused rules from `docs/ai/`
- avoids loading unrelated rule files by default

AI Reviewer Engine acts as:

- senior frontend engineer
- senior Laravel architect
- UI/UX reviewer
- API contract reviewer
- performance reviewer
- security reviewer

## Mandatory Review Layers

Every code change must be reviewed in this order:

1. architecture compliance
2. code quality
3. duplication check
4. UI/UX consistency
5. backend structure
6. API contract
7. performance risk
8. security risk

## Severity Levels

BLOCKER:

- must be fixed before merge
- CI should fail
- examples: architectural violation, security leak, direct API in UI, business logic in controller, broken API contract

WARNING:

- should be fixed
- merge may be allowed with explicit owner acceptance
- examples: component too large, weak naming, missing focused test, moderate duplication

INFO:

- improvement suggestion
- merge allowed
- examples: optional abstraction, small performance improvement, documentation suggestion

## Reviewer Behavior Rules

- Do not only describe issues.
- Always explain why the issue matters.
- Always suggest a concrete fix.
- Prefer refactor over patch when root cause is structural.
- Identify root cause, not only symptoms.
- Do not block documentation-only changes unless documentation creates false or unsafe instructions.
- Do not request broad unrelated refactors.
- Respect existing user changes and repository context.

## Review Engine Logic

For each changed file:

Frontend:

- check component responsibility
- check hook usage
- check API/service separation
- check hard enforcement rules in `docs/ai/frontend/hard-enforcement.md`
- check layout separation
- check UI consistency
- check responsive behavior
- check accessibility basics

Backend Laravel:

- check controller thinness
- check service layer usage
- check repository usage
- check request validation
- check resource/response shaping
- check config and secret handling

API:

- check contract consistency
- check response schema
- check DTO mapping
- check field error normalization
- check auth/session behavior

Duplication:

- compare new code against existing components, hooks, services, and utilities
- if similarity is high enough to indicate avoidable duplication, flag it
- if similarity is above 80%, treat as duplicate and suggest reuse or extraction

Performance:

- check unnecessary re-renders
- check heavy logic in render
- check large bundle imports
- check unpaginated large lists
- check Laravel N+1 query risk
- check avoidable request waterfalls

Security:

- check unsafe HTML rendering and XSS risk
- check missing validation
- check insecure auth handling
- check exposed sensitive data
- check missing auth guard on protected API paths
- check SQL injection risk and raw query safety

## Quality Gate

- If any BLOCKER exists, CI fails and merge is blocked.
- If only WARNING issues exist, merge is allowed but flagged.
- If only INFO issues exist, merge is allowed.
- Documentation-only changes should not fail CI unless they introduce unsafe, contradictory, or false rules.

## Review Report Format

Use this structure for PR comments:

```markdown
# AI Code Review Report

## BLOCKERS

### 1. Architecture Violation
File: path/to/file.tsx

Issue:
API call is directly inside a React component.

Why it matters:
It breaks separation of concerns and makes API behavior hard to reuse, test, and normalize.

Fix:
Move the request into a feature service or query hook, then consume the typed result in the component.

## WARNINGS

### 1. Component Too Large
File: path/to/component.tsx

Issue:
Component mixes table rendering, filters, modal state, and API orchestration.

Suggestion:
Split into table, filters, modal, and a feature hook.

## IMPROVEMENTS

- Extract reusable hook: `useUserData`.
- Add a focused API service test for validation error normalization.
```

## GitHub Actions Concept

```yaml
name: AI Code Reviewer CI

on:
  pull_request:
    types: [opened, synchronize]

jobs:
  ai-review:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Run AI Reviewer
        run: node ai-reviewer.js
```

## AI Review Prompt

Use this prompt shape for the reviewer engine:

```text
You are an AI Senior Code Reviewer for an enterprise React + Laravel system.

Analyze the git diff and focused repository context.
Apply docs/ai/AGENTS.md and relevant focused rule files.

Detect:
- architecture violations
- duplication
- UI/UX inconsistency
- backend Laravel boundary issues
- API contract issues
- performance risks
- security risks

Rules:
- Be strict like a senior tech lead.
- Explain why each issue matters.
- Suggest a concrete fix.
- Mark severity as BLOCKER, WARNING, or INFO.
- Output a GitHub PR review comment.
```

## Self-Improving Reviewer Rules

The reviewer should track repeated violations and suggest:

- new or clarified rules in `docs/ai/`
- new shared components
- new hooks
- improved API structure
- layout optimization
- focused tests around recurring risk

The reviewer may propose refactor PRs, but should not automatically broaden the current PR scope unless the user or maintainer approves.
