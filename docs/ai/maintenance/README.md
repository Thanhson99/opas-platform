# AI Self-Maintaining Dev System

This folder turns AI into a maintenance engineer, not only a code generator.

## Core Responsibility

AI must act as:

- code generator
- code reviewer
- architecture guardian
- refactoring engine
- consistency maintainer

## System Layers

1. Code layer
   - React UI
   - Laravel API
2. Rule layer
   - `AGENTS.md`
   - `docs/ai/*`
   - `docs/codegen-rules.md`
   - `docs/rules/*`
3. Validation layer
   - detect violations
   - check consistency
   - enforce rules
4. Refactor layer
   - suggest improvements
   - normalize structure
   - remove duplication
5. Evolution layer
   - improve architecture over time
   - propose system upgrades

## Mandatory Pre-Action Check

Before writing code, AI must check:

1. existing implementation
2. relevant architecture rules
3. duplication risk
4. consistency with existing patterns
5. likely impact on layout, API, state, and tests

If violation risk exists, stop direct implementation, report the risk, and propose a compliant refactor or implementation plan first.

## Violation Detection

Frontend violations:

- API call inside a component
- data fetching directly inside component `useEffect`
- layout shell imported directly inside a page
- Header, Footer, or Sidebar imported directly inside a page
- business logic inside JSX
- duplicate component or duplicated UI logic
- component with multiple responsibilities
- component exceeds the applicable size limit without clear reason
- prop drilling beyond two levels
- inline styles or arbitrary design values

Backend violations:

- business logic inside controller
- direct DB query or query orchestration outside repository/model boundary
- inconsistent API response shape
- plaintext secrets or privileged config exposed in responses
- runtime config read directly from `env()` outside config files

API violations:

- raw transport client used inside component
- raw Laravel response envelope exposed to UI component
- endpoint strings scattered across UI files
- validation errors not normalized for forms

UI violations:

- inconsistent spacing
- hardcoded styles or colors that bypass tokens
- non-responsive layout
- horizontal mobile overflow
- hover-only interaction
- important state communicated only by color

## AI Action Rule

If a violation is detected:

1. Do not immediately write more feature code.
2. Report the violation.
3. Propose a fix plan.
4. Implement the compliant fix when it is in scope.
5. Validate again after the fix.

If the requested change would violate architecture, refuse direct implementation and propose a compliant alternative.

If a violation is pre-existing and unrelated to the requested change, report it as a separate follow-up instead of expanding scope automatically.

For strict frontend blocker rules, also apply `docs/ai/frontend/hard-enforcement.md`.

## Refactor Triggers

AI must suggest or perform a scoped refactor when:

- duplication is detected
- component exceeds the applicable size limit
- multiple responsibilities are found
- layout inconsistency exists
- API inconsistency exists
- backend controller owns business logic
- repository/service boundaries are blurred
- repeated hook/service/utility logic appears

Refactor before adding new feature code if the local system is unstable enough that the new feature would deepen the problem.

## Refactor Actions

UI refactor:

- split components
- extract reusable UI blocks
- normalize props structure
- move repeated UI into shared or feature-local components

Logic refactor:

- move stateful logic to hooks
- move pure logic to utilities
- move API calls to service layer
- normalize DTO mappers

Backend refactor:

- move controller logic to services
- move reusable queries to repositories
- normalize resources and API responses
- add request validation where boundary validation is missing

Layout refactor:

- move shell code into layout components
- move navigation items into config
- make Header, Footer, Sidebar replaceable
- select layouts through route metadata or layout registry

## Post-Change Validation

After writing code, AI must verify:

- architecture boundaries are still respected
- no duplicate logic or duplicate UI was introduced
- layout rules are not violated
- API layer is respected
- UI is responsive and reusable
- state ownership is clear
- naming conventions are followed
- relevant tests or checks were run

## Quality Gate

No code is considered complete unless:

- architecture rules pass
- no avoidable duplication remains
- API calls use service/query layer
- layout remains decoupled and swappable
- UI uses reusable components and token-based styling
- responsive behavior is considered
- naming conventions are followed
- verification result is reported

## Continuous Improvement Loop

Every time AI modifies the codebase:

1. analyze impact
2. detect pattern repetition
3. suggest abstraction opportunities when useful
4. suggest architectural improvement when drift is visible

AI may suggest:

- new shared components
- new hooks
- improved API structure
- layout optimization
- stricter tests around platform code

Do not introduce broad architectural changes without clear scope and user approval.
