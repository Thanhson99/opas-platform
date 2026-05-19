# Docblock Rules

## Scope

Apply these rules to PHP code in `apps/laravel/app/`.

When tests introduce helper methods, data builders, or non-trivial setup logic in `apps/laravel/tests/`,
apply the same method-level documentation standards there as well.

## Method Rules

- Every public and protected PHP method in `app/` must have a docblock with a one-line summary.
- Private PHP methods should also have a docblock when they contain branching, data shaping, persistence rules, auth rules, or non-trivial formatting logic.
- Every documented method must include `@return`, even when the native PHP return type is obvious.
- Every documented method with parameters must include `@param` for each parameter.
- Array-shaped payloads must declare their array shape in `@param` or `@return` whenever practical.
- PHPUnit test methods should include a concise summary docblock and `@return void`.
- Test helper methods with parameters or non-trivial behavior must include `@param` and `@return`.

## Class-Level Rules

- Add a class docblock when the class owns a non-obvious domain responsibility or generic type information important to static analysis.
- Repository classes extending generic bases should declare `@extends` types explicitly.
- Resource or collection classes should document transformed payload shapes when the shape is non-trivial.

## Quality Rules

- Keep docblocks concise and factual.
- Avoid docblocks that restate the function name without adding meaning.
- Document behavior and contract, not implementation noise.
- If a method exists mainly to enforce a business rule, the summary should make that rule explicit.
- Prefer imperative clarity over prose. One strong sentence is better than three weak ones.
- Keep terminology consistent with the domain and the API contract.
- In tests, summarize the business rule or scenario being protected instead of restating the test method name.

## Review Checklist

- The summary explains why the method exists.
- Parameters reflect real runtime expectations.
- `@return` matches the actual contract.
- Complex arrays are typed clearly enough for maintenance and static analysis.
