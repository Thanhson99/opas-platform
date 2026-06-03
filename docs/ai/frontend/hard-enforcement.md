# Frontend Hard Enforcement System

This file defines strict blocker-level frontend rules for React + TypeScript. Violations should be treated as merge blockers unless explicitly approved by an architecture owner.

## Core Principle

Frontend operates under strict enforcement:

1. UI is a pure render layer.
2. Business logic is forbidden in components.
3. API calls are forbidden outside the service/API layer.
4. Layout is an immutable structure layer.
5. Data flow must be predictable and traceable.
6. No exceptions without architectural approval.

## Hard Block Rules

The following are absolute blockers:

- API call inside component in any case.
- `fetch`, Axios, or transport client usage inside component.
- `useEffect` used for direct data fetching in a component.
- Inline business logic inside JSX.
- Mutation of props.
- Duplicated UI components.
- Inline style usage.
- Any usage of `any` in new TypeScript.
- Direct state sync between unrelated components.
- Bypassing the service layer.
- Layout shell code inside page.
- Header, Footer, or Sidebar imported directly by a page.

If detected, stop implementation, report the blocker, propose a refactor plan, then continue only with a compliant approach.

## Mandatory Frontend Flow

Do not skip layers:

```text
Page
  -> Layout
  -> Container Component
  -> Hook Layer
  -> Service Layer
  -> API Client Layer
  -> Laravel API
```

Layer responsibilities:

- Page: route-level content composition only.
- Layout: wrapper structure only.
- Container component: UI orchestration without business logic or API transport.
- Hook layer: stateful feature orchestration and business-flow coordination.
- Service layer: API calls, DTO mapping, and API contract normalization.
- API client layer: HTTP transport, auth headers, CSRF, cancellation, timeout, and normalized errors.

## Component Rules

Every component must follow:

- maximum 150 LOC for new or heavily edited frontend components
- single responsibility only
- explicit typed props
- no internal API logic
- no mutation outside React state setters or approved state utilities
- loading, error, and empty state support when rendering remote data

## UI System Lock

UI is strictly token-based.

Allowed:

- spacing: `4, 8, 12, 16, 24, 32`
- radius: `sm, md, lg`
- colors: theme tokens only

Forbidden:

- arbitrary hex colors in components
- inline styles
- random margin or padding values
- one-off button styles

## Responsive Rule Lock

Rules:

- mobile-first only
- no fixed width except stable containers or fixed-format controls
- no pixel-based layout lock for responsive surfaces
- must support mobile, tablet, and desktop

Forbidden:

- overflow hacks
- negative margins for layout fixes
- horizontal page scroll

## API Layer Enforcement

Forbidden:

- Axios/fetch/transport client inside component
- direct API call in UI component or render-oriented hook
- endpoint strings in components

Required:

```text
Component -> Hook -> Service -> API Client -> Laravel API
```

Service layer is the only frontend feature layer allowed to call the API client.
Hooks may call services or query hooks, but must not call transport clients directly.

## State Management Lock

Rules:

- UI state uses local state.
- Server state uses React Query / TanStack Query where available.
- Global state is only for shared domain state with a clear owner.

Forbidden:

- duplicated state sources
- manually stored derived state when it can be computed cheaply
- prop drilling beyond two levels
- direct state sync between unrelated components
- secrets or raw API envelopes in global state

## Layout Immutability Rule

Layout system is immutable from the page's perspective.

- Pages never import Header, Footer, or Sidebar.
- Layout controls structure only.
- Layout cannot contain page business logic.
- Layout swapping must not affect page code.

Supported layout types:

- `DefaultLayout`
- `AuthLayout`
- `DashboardLayout`
- `MinimalLayout`
- `CustomLayout`

## Code Quality Hard Rules

- No frontend helper or event handler over 30 lines unless extraction would reduce clarity.
- Service orchestration methods may exceed 30 lines only when phases are clear and named.
- No component over 150 LOC for new or heavily edited frontend code.
- No duplicated logic.
- No magic values.
- No inline complex expressions in JSX.
- Extract reusable logic immediately when duplication appears.

## Kill Switch

If any of the following is detected:

- API in component
- duplicated component
- layout misuse
- UI logic mixed with business logic
- raw transport inside UI
- `any` in new TypeScript

AI must stop implementation, report the issue, propose a compliant refactor, then continue only after the approach is clear.

## Frontend CI Gate Checklist

Before merge, all must pass:

- no API in UI layer
- no layout violation
- no avoidable duplication
- responsive behavior considered
- fully typed for new TypeScript, no `any`
- service layer respected
- hook separation respected
- UI system tokens respected
- no inline complex JSX logic
- no prop mutation

## AI Behavior Mode

AI must behave as:

- senior frontend architect
- strict code reviewer
- refactoring engineer

If unsure, do not guess. Analyze first or ask for clarification. Never prioritize speed over architecture correctness.
