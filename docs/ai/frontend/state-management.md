# State Management

## Ownership Rules

- UI state uses local component state.
- Remote server data belongs in query/cache hooks or feature hooks.
- Auth/session state belongs in an app provider.
- Theme and locale belong in app providers.
- Shared client-only state can use a global store only when there is a clear owner and reason.
- Do not duplicate state.
- Do not store derived state manually when it can be computed cheaply.
- Do not store secrets.
- Do not store raw API response envelopes.

## Preferred Tools

- Server state: React Query / TanStack Query where available; SWR is acceptable if it is already the project standard.
- Lightweight client global state: Zustand.
- Complex enterprise workflow state: Redux Toolkit only when event/history/debug needs justify it.

## Prop Drilling Rule

- Passing props one level is fine.
- Passing props two levels is acceptable.
- More than two levels requires composition, feature context, or hook extraction.

## Form State Rules

- Keep form state close to the form.
- Use typed payloads.
- Backend validation errors map predictably to fields.
- Do not submit raw uncontrolled objects without normalization.
