# Core AI Behavior

## Core Principles

1. AI must understand architecture before coding.
2. UI, logic, API access, and persistence must stay separated.
3. Layouts are independent, composable, and swappable.
4. Laravel is the source of truth for persisted data and backend rules.
5. React is a rendering and interaction layer that consumes typed contracts.

## Mandatory Behavior

- Always inspect existing structure before coding.
- Never assume project architecture.
- Never bypass the API/service layer.
- Never hardcode business logic in pages or JSX.
- Always reuse existing components, hooks, services, and layout primitives before creating new ones.
- Preserve user changes and avoid unrelated rewrites.
- Prefer stable production patterns over experimental abstractions.
- Run narrow verification before closing a task and report any blocker.

## Output Expectation

Generated code and docs must be clean, typed where applicable, scalable, production-ready, consistent with existing patterns, and free of experimental architecture unless explicitly requested.
