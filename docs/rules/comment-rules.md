# Comment Rules

## Purpose

Comments should make code easier to maintain, not compensate for weak naming or poor structure.

## When To Add Comments

- Add a comment when a block enforces a non-obvious business rule.
- Add a comment when a block protects a security, auth, or data-integrity constraint.
- Add a comment when the code performs non-trivial normalization, formatting, or branching that would otherwise cost time to re-derive.
- Add a short section comment when a method has a necessary but dense block that cannot be simplified further without making the code worse.
- Add short section comments in shared config files when multiple feature areas or workflow safety settings live in the same array and the purpose of each block would otherwise be ambiguous.

## When Not To Add Comments

- Do not comment obvious assignments, conditionals, or framework boilerplate.
- Do not narrate what the code literally says line by line.
- Do not use comments to excuse a large method that should instead be split.
- Do not duplicate information already made clear by method names, variable names, or types.

## Style Rules

- Keep comments short, direct, and factual.
- Prefer explaining intent or constraint over mechanics.
- Use complete phrases or short sentences.
- Update or delete comments when the code changes. Stale comments are bugs.
- Keep comment tone neutral and technical. Avoid conversational or speculative phrasing.
- If a comment describes a system safety rule, phrase it as a rule, not as a guess.
- Prefer one strong comment above a block over multiple inline comments that narrate each line.
- Do not use comments as a substitute for extracting a method with a clear name.
- Keep comment lines short enough to scan quickly (target <= 120 characters).
- Use comments to declare invariants and safety constraints, not temporary debugging context.
- Remove or rewrite comments immediately when behavior changes; stale comments fail review.

## Preferred Comment Targets

- business-rule exceptions
- security constraints
- data-integrity safeguards
- external API quirks or protocol edge cases
- query or transaction constraints that are non-obvious from the code alone

## Preferred Pattern

- First improve naming and structure.
- Then add the smallest comment that explains the remaining non-obvious part.
- For strict clean-code review, every new comment should pass this check:
- cannot be replaced by better naming
- documents a real business/safety constraint
- remains true across expected refactors

## Frontend Comment Rules

- In `resources/js`, prefer JSDoc for reusable functions, components, contexts, config contracts, and entry modules, and reserve regular comments for non-obvious UI rules, backend-contract constraints, or provider-specific quirks.
- In `resources/js`, use JSDoc as the primary documentation mechanism for exported functions and components instead of replacing that documentation with ad hoc inline comments.
- Do not add comments above every React hook or state setter. Comment only the workflow rule that would otherwise be easy to misread.
- When frontend logic mirrors a backend safety rule, say that explicitly in one short comment instead of narrating the branch line by line.
- For formatting helpers, fallback label builders, and payload normalizers, add a short comment only if the function name and JSDoc still leave an important constraint implicit.
- When a frontend contract is reused across helpers or contexts, prefer one local `@typedef` plus focused JSDoc over repeating vague inline comments on every consumer.

## Route File Comment Rules

- In `routes/api.php`, `routes/web.php`, and `routes/console.php`, organize related definitions into clearly named sections with one short comment above each section.
- Route-file section comments should explain the feature area, audience, or operational boundary, not narrate individual route lines.
- Prefer section comments such as auth lifecycle, admin control plane, machine-facing endpoints, SPA entrypoints, reporting commands, or maintenance commands.
- When a route group introduces a non-obvious middleware or trust boundary, say that in the section comment once instead of repeating comments inside the group.
- In `routes/console.php`, use section comments to separate command families, and rely on each command's `purpose()` text for the command-level description.
- Do not add one comment above every single route or command when a grouped section comment makes the file clearer with less noise.
