# Comment Rules

## Purpose

Comments should make code easier to maintain, not compensate for weak naming or poor structure.

## When To Add Comments

- Add a comment when a block enforces a non-obvious business rule.
- Add a comment when a block protects a security, auth, or data-integrity constraint.
- Add a comment when the code performs non-trivial normalization, formatting, or branching that would otherwise cost time to re-derive.
- Add a short section comment when a method has a necessary but dense block that cannot be simplified further without making the code worse.

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

## Preferred Pattern

- First improve naming and structure.
- Then add the smallest comment that explains the remaining non-obvious part.
