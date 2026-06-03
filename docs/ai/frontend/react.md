# React Enterprise Rules

## TypeScript Rules

- New React code uses TypeScript strict mode.
- Legacy JavaScript may remain, but new or heavily edited modules should move toward typed contracts when practical.
- Do not use `any`.
- Use `unknown` at external boundaries, then narrow or map into typed contracts.
- Export explicit types for reusable props, API payloads, DTOs, and hook return values.

## Component Rules

- Functional components only.
- No class components.
- One component, one responsibility.
- Components should stay under 200 lines by default.
- When `hard-enforcement.md` applies, new or heavily edited frontend components should stay under 150 lines.
- Pages should stay under 150 lines when practical.
- Remote data components must support loading, error, and empty states.
- Prefer composition over boolean-heavy prop APIs.
- Avoid more than 8 props. Group related props into typed objects.
- Avoid prop drilling beyond two levels.

## Hook Rules

- Hooks own reusable frontend stateful logic.
- Hooks may call services.
- Hooks may normalize UI state.
- Hooks must not render JSX.
- Hook names must start with `use`.
- Reused hooks must expose a stable typed return contract.

## API Rules

- Never call APIs directly inside components.
- Components call feature hooks or query hooks.
- Hooks may call feature services.
- Hooks must not call transport clients directly.
- Endpoint strings stay in services.
- Request and response mapping belongs in service mappers, DTO helpers, hooks, or pure utilities.

## Reusability Rules

- Extract UI when the same visual pattern appears twice.
- Extract logic when the same condition, mapping, or formatter appears twice.
- Extract a shared component only when it is product-neutral.
- Keep feature-specific language and behavior inside the feature.
