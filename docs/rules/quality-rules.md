# Quality Rules

## Purpose

Use these rules to keep PHP and Laravel code readable, typed, maintainable, and efficient before review is needed.

## Method Size And Complexity

- Prefer methods that fit on one screen and do one job.
- As a default heuristic:
  - target up to 25 logical lines for simple methods
  - review methods above 40 logical lines for extraction
  - strongly prefer refactoring methods above 60 logical lines unless the structure is unusually linear and clear
- If a method has more than 3 decision branches, look for named private method extraction.
- If a method needs section comments for multiple unrelated phases, split the phases into named methods.

## Parameter And Return Rules

- Prefer up to 4 parameters for public methods.
- At 5 or more parameters, stop and check whether the method is carrying too many responsibilities or needs a structured payload object/array shape.
- Boolean parameters are a design smell when they change behavior significantly. Prefer separate methods or a named option structure.
- Public methods should declare native return types whenever possible.
- Use precise collection, array-shape, and nullable docblocks when native PHP types are not expressive enough.

## Typing Rules

- Add scalar, object, nullable, and return types wherever supported.
- Avoid mixed input/output unless the boundary genuinely requires it.
- Normalize arrays before use when input may be mixed.
- Prefer enums or dedicated constants over free-form magic strings for stable states.
- Cast model attributes explicitly when the domain depends on type correctness.

## Naming Rules

- Name methods by business outcome or domain action.
- Name variables by domain meaning.
- Avoid vague names such as `handleData`, `processItem`, `temp`, `obj`, `info`, `arr`, or `check`.
- Collection variables should read like collections, for example `users`, `providers`, `linkedAccounts`.
- Single-item variables should read like a single entity, for example `user`, `provider`, `identity`.
- Boolean names should answer a yes/no question clearly, for example `isActive`, `hasVerifiedEmail`, `shouldSyncProfile`.

## Control Flow Rules

- Prefer guard clauses and early returns over nested `if` pyramids.
- Avoid more than 2 nested condition levels in normal code.
- Replace branch-heavy inline logic with named private methods that state the rule being applied.
- Prefer explicit failure branches over silent mutation or fallback.

## Query Efficiency Rules

- Do not call the database repeatedly inside loops when the data can be loaded once.
- Use eager loading when a response or workflow needs related models for multiple records.
- Select only the fields needed when a query returns large datasets or powers admin listings.
- Centralize reusable query filters and ordering in repositories or scopes.
- When introducing pagination, sorting, or filtering, make the contract explicit and deterministic.

## Data Shaping Rules

- Normalize input once near the boundary, then work with predictable shapes.
- Do not pass oversized associative arrays through multiple layers when a smaller, explicit payload is enough.
- If multiple methods rely on the same complex payload structure, document the array shape or introduce a dedicated object/value object when justified.
- Keep resource output field names stable and explicit.

## Error And Safety Rules

- Raise explicit validation or domain exceptions when input or state violates a business rule.
- Error messages returned to clients should be stable, safe, and useful without leaking internals.
- Logs may contain operational context but must not leak secrets or sensitive raw payloads.
- Security and integrity rules should be obvious from naming, structure, and targeted comments.

## Comment And Doc Discipline

- Improve names and method structure before adding comments.
- Add docblocks for public/protected methods according to `docblock-rules.md`.
- Add comments only where the intent or safety rule would otherwise remain non-obvious after cleanup.
- If a method still needs many comments, it probably needs to be split.

## Refactor Triggers

- Extract a private method when a branch has a clear business name.
- Extract a service when controller logic starts coordinating more than one domain concern.
- Extract a repository method when a query is reused or carries non-trivial constraints.
- Extract a value object or enum when the same shape or state semantics appear repeatedly.
- Split a class when new methods belong to a different reason to change.

## Practical Clean-Code Checklist

- Method names describe intent.
- Variables reflect domain meaning.
- Public methods are typed.
- Long or branch-heavy methods are split.
- Queries are intentional and reusable.
- Model casts and relationships are explicit.
- Services hold business logic.
- Controllers stay thin.
- Comments are sparse and meaningful.
- Docblocks match real behavior and real types.
