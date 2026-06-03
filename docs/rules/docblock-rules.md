# Docblock Rules

## Scope

Apply these rules to PHP code in `apps/laravel/app/`.

When tests introduce helper methods, data builders, or non-trivial setup logic in `apps/laravel/tests/`,
apply the same method-level documentation standards there as well.

Apply the same documentation discipline to frontend code in `apps/laravel/resources/js/`
using concise JSDoc blocks instead of PHP docblocks.

For `apps/laravel/routes/api.php`, `routes/web.php`, and `routes/console.php`,
prefer concise section comments over docblocks on executable route or command definitions.

## Method Rules

- Every public and protected PHP method in `app/` must have a docblock with a one-line summary.
- Pure dependency-injection constructors do not need a docblock.
- Add a constructor docblock only when the constructor enforces a business rule, performs non-obvious setup, or needs generic/static-analysis explanation.
- Private PHP methods should also have a docblock when they contain branching, data shaping, persistence rules, auth rules, or non-trivial formatting logic.
- Enums that define reusable workflow states, status contracts, or recommendation outputs should carry a short class docblock explaining what contract they represent.
- When a config file introduces non-obvious feature sections or safety-sensitive settings, add short section comments so operators can identify the purpose of each block quickly.
- When a route file contains multiple feature areas or command families, add short section comments so operators can scan the file by boundary instead of reading each definition line by line.
- Every documented method except `__construct` must include `@return`, even when the native PHP return type is obvious.
- Every documented method with parameters must include `@param` for each parameter.
- Array-shaped payloads must declare their array shape in `@param` or `@return` whenever practical.
- Use explicit scalar/object union shapes in `@param` and `@return` when runtime input can vary, for example `int|string|null`.
- Keep `@param` names exactly aligned with method signatures.
- Keep docblock contracts synchronized with native types after each refactor.
- PHPUnit test methods should include a concise summary docblock and `@return void`.
- Test helper methods with parameters or non-trivial behavior must include `@param` and `@return`.
- Every `export default function` in `resources/js` must have a concise JSDoc summary block.
- Exported JS/React functions in `resources/js` that shape data, own UI/business branching, transform API payloads, provide reusable feature behavior, define a shared contract, or bootstrap app/runtime behavior must have a JSDoc block.
- Non-exported JS/React helpers should also have a JSDoc block when they perform non-trivial normalization, fallback resolution, or rendering-related branching.
- React components should always have a short JSDoc summary when exported, and should also document props with `@param` when the prop list is non-trivial.
- Simple leaf callbacks, tiny inline render helpers, and obvious one-line utilities do not need JSDoc unless the surrounding file depends on them as a stable contract.
- JS test helpers with non-trivial setup or reusable provider fixtures should have a short JSDoc block that explains the scenario they build.
- Add `@returns` to frontend JSDoc when the function returns a non-obvious value, a reusable data contract, or a JSX element from a shared component.
- Component JSDoc should include an explicit props shape when the component has more than 3 props, accepts function props, or exposes variant/tone/layout options.
- Helper JSDoc should include parameter shapes when the helper consumes structured objects whose keys are part of the contract.
- For shared contexts, prefer local `@typedef` contracts over generic `Record<string, any>` return shapes.
- For shared config arrays or reusable payload builders, prefer named `@typedef` contracts when more than one field matters to consumers.

## Class-Level Rules

- Add a class docblock when the class owns a non-obvious domain responsibility or generic type information important to static analysis.
- Repository classes extending generic bases should declare `@extends` types explicitly.
- Resource or collection classes should document transformed payload shapes when the shape is non-trivial.

## Quality Rules

- Keep docblocks concise and factual.
- Keep summaries to one short sentence. Prefer roughly 4 to 12 words when practical.
- Avoid docblocks that restate the function name without adding meaning.
- Avoid placeholder summaries such as `Inject ...`, `Create one ... service`, or other boilerplate that only narrates DI wiring.
- Document behavior and contract, not implementation noise.
- If a method exists mainly to enforce a business rule, the summary should make that rule explicit.
- Prefer imperative clarity over prose. One strong sentence is better than three weak ones.
- Keep terminology consistent with the domain and the API contract.
- In tests, summarize the business rule or scenario being protected instead of restating the test method name.
- For JSDoc in frontend code, prefer a one-line summary plus `@param` or `@returns` only when they add clarity.
- If TypeScript-style detail would repeat obvious runtime shapes, keep the JSDoc lighter instead of adding noise.
- Prefer documenting the data contract or workflow rule over DOM mechanics.
- For shared components and context providers, prefer documenting the props or return contract once in the JSDoc rather than scattering explanatory comments through the render body.
- Do not use docblocks to justify avoidable complexity. Split code first, then document the stable contract.
- If a method has 5 or more parameters, document the payload strategy explicitly in the summary or convert to a typed payload array/object.

## Review Checklist

- The summary explains why the method exists.
- Constructor docblocks exist only when they add contract value.
- Parameters reflect real runtime expectations.
- `@return` matches the actual contract.
- Complex arrays are typed clearly enough for maintenance and static analysis.
- Frontend helpers with branching or normalization expose a clear JSDoc contract.
- React components with feature orchestration have a short summary block.
- Every exported frontend component/function has at least a summary JSDoc block.
- Shared components with multiple props or callbacks document their props shape with `@param`.
