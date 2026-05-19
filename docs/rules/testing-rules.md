# Testing Rules

## Coverage Baseline

- Add or update feature tests for user-facing behavior changes.
- Add or update unit tests for service-level branching or validation rules.
- For auth changes, cover success path, denial path, and invalid configuration path where relevant.
- Every bug fix should add or update a regression test that fails without the fix.

## Test Design Rules

- Prefer one assertion theme per test.
- Split large scenario tests into smaller tests when they verify different rules.
- Name tests from the business rule outward.
- Tests must describe system behavior, not implementation trivia.
- Every PHPUnit test method should carry a concise docblock summary and `@return void`.
- Test helper methods must document `@param` and `@return` when they accept input or hide non-trivial setup.
- Assert observable contract first and internal state second.
- Keep fixture data minimal and explicit.
- Avoid hardcoded environment-specific URLs, secrets, and credentials in tests.
- Prefer representative fake values such as `example.com`, `test-secret`, and route-generated callback URLs.
- Keep tests deterministic:
  - fake notifications, queues, mail, and HTTP where applicable
  - avoid time-sensitive assertions unless the behavior is explicitly time-based
  - avoid external network or third-party dependency calls
- Prefer asserting exact business-relevant fields over broad array comparisons.
- Add positive-path tests for valid behavior and rejection-path tests for invalid or forbidden behavior when both matter to the rule.
- Avoid over-mocking first-party application code. Prefer real service wiring unless the test is specifically about isolation.

## Admin Configuration Coverage

- For admin-managed configuration features, cover at minimum:
  - authorized success path
  - unauthorized or forbidden access path
  - invalid payload rejection
  - persistence or state transition result
  - public contract impact when the setting affects end-user behavior

## Auth Provider Coverage

- For auth provider changes, cover at minimum:
  - provider listing visibility rules
  - readiness or incomplete-config rules
  - secret persistence without plaintext exposure
  - provider-specific verification policy behavior
  - baseline sign-in safety rules that prevent total lockout
- When a feature returns sensitive metadata, add an explicit assertion that secret values are absent from responses.
- When a setting changes downstream auth behavior, add at least one test at the consumer boundary, not only at the admin update endpoint.
- When validating structured arrays, assert the smallest meaningful error path such as `public_config.redirect_uri`.
- When a business rule protects system safety, add both a positive test and a negative regression test whenever practical.

## Test Structure Rules

- Organize tests by the application layer that owns the behavior:
  - `tests/Feature/Controllers/...` for route, middleware, request validation, response contract, and end-to-end persistence checks
  - `tests/Unit/Services/...` for service branching, orchestration rules, fallback logic, and validation exceptions
  - `tests/Unit/Repositories/...` for query ordering, lookup behavior, persistence refresh behavior, and repository-specific data access rules
  - `tests/Unit/Http/Resources/...` only when resource transformation contains meaningful branching or data shaping worth isolating
- Do not create controller unit tests. Controller behavior should be covered through HTTP feature tests.
- Do not add repository tests by default for trivial pass-through CRUD. Add them when the repository owns query ordering, filtering, lookup rules, or refresh semantics that could regress.
- Request validation is normally covered by feature tests against the endpoint that consumes the request.
- Resource transformation is normally covered by feature tests against the endpoint that returns the resource.
- Add a dedicated unit test only when a request, resource, or helper contains branching or data shaping complex enough to justify isolated coverage.
- Mirror production namespaces in test folders when practical so files are easy to locate and maintain.
- If a feature touches controller, service, and repository layers with meaningful logic in each layer, prefer adding tests in each corresponding folder instead of forcing all assertions into a single feature test file.
- If a resource hides or reshapes sensitive fields, prefer a focused resource unit test in addition to controller coverage.

## Maintenance Rules

- Keep test helpers local to the test class unless at least three test classes need the same abstraction.
- Do not hide important setup in generic helpers when inline setup is clearer for auth or security-sensitive behavior.
- Before closing a task, verify the narrowest relevant PHPUnit scope locally instead of assuming broader suites will cover the change.
- When test docblocks drift or become inconsistent, update both the affected tests and the relevant rules file in the same change.
