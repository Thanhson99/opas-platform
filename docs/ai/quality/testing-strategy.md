# Testing Rules

## Frontend Required Coverage

- Unit tests for utilities, mappers, reducers, and validation helpers.
- Component tests for important loading, error, empty, and success states.
- Integration tests for feature workflows that combine hooks, services, and UI.
- Mock API at the service/client boundary.

## Component Test Checklist

- renders primary content
- shows loading state
- shows empty state
- shows error state
- calls callbacks on user interaction
- disables submit while saving
- maps validation errors to fields

## API Service Test Checklist

- correct endpoint and method
- payload normalization
- DTO-to-domain mapping
- validation error normalization
- auth/server/network error handling

## Backend Testing

- Follow `docs/rules/testing-rules.md`.
- Add feature tests for user-facing backend behavior changes.
- Add unit tests for service-level branching or validation rules.
- Every bug fix should add or update a regression test where practical.

## Testing Rules

- Test behavior, not implementation details.
- Keep tests deterministic.
- Prefer narrow tests first, then broader tests when change risk requires it.
