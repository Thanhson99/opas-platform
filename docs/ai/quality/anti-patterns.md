# Forbidden Patterns

## Architecture

- Organizing a large app only by `components`, `hooks`, and `services` without feature boundaries.
- Importing page modules into lower-level components.
- Letting shared components depend on feature modules.
- Duplicating route layout wrappers in pages.
- Hardcoding layout in pages.

## Frontend

- API call inside component.
- Huge monolithic components.
- Inline business logic in JSX.
- Untyped objects passed through many layers.
- Boolean prop combinations that create unclear behavior.
- Duplicated UI logic across pages.
- Any use of `any` in new or heavily edited TypeScript.

## Layout

- Header/Footer/Sidebar imported directly by pages.
- Layouts fetching page business data.
- Sidebar navigation hardcoded inside page JSX.

## API

- Raw Laravel response envelopes exposed to UI components.
- Endpoint strings scattered across components.
- Raw backend exception messages shown to users.
- Secrets stored in local storage, global state, or component state.
- Feature services coupled to React rendering.

## UI

- Random spacing values.
- Random colors.
- One-off button styles.
- Layouts that require horizontal scrolling on mobile.
- Hover-only controls.
- State communicated through color only.

## Performance

- Heavy libraries imported globally for one route.
- Large derived datasets computed during render.
- Huge lists rendered without pagination or virtualization.
- Memoizing everything blindly.
- Array index keys for mutable lists.
