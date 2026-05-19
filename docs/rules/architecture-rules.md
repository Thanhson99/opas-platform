# Architecture Rules

## Purpose

Use these rules to keep Laravel application code organized around clear responsibilities, stable boundaries, and reusable feature structure.

## Default Architecture

- Treat `apps/laravel` as a layered modular monolith unless a task explicitly introduces a separate service boundary.
- Prefer cohesive feature slices over scattering one feature across unrelated folders without reason.
- Keep transport, business rules, persistence, and presentation concerns separated even when they live in the same repository.
- Do not introduce "microservice-style" complexity inside the monolith unless the boundary is operationally real and justified.

## Controller Rules

- Controllers are HTTP adapters.
- Controllers should:
  - authorize access
  - validate request input
  - call one primary service or orchestrator
  - return resources, JSON responses, redirects, or status responses
- Controllers must not contain:
  - multi-step domain workflows
  - raw query composition
  - transaction orchestration unless there is no service yet and the task is tiny
  - data reshaping that belongs in resources
- If a controller action needs more than a few local variables or multiple business branches, move the logic into a service.

## Request And Validation Rules

- Request classes own HTTP-facing validation rules and authorization checks when practical.
- Request validation should cover shape, required fields, primitive constraints, and simple field relationships.
- Cross-record checks, business invariants, and persistence-aware validation belong in services.
- If multiple endpoints share the same validated payload semantics, prefer a dedicated request class over inline validation.

## Service Rules

- Services own business workflows and domain coordination.
- A service should expose a small set of public methods that reflect business capabilities, not technical steps.
- Public service methods should usually represent one use case, one workflow, or one business action.
- Services may coordinate repositories, models, events, notifications, policies, and transactions.
- Services should not:
  - build HTTP responses
  - read raw request objects unless the service is explicitly transport-facing
  - contain large inline SQL/query-builder logic that belongs in repositories
  - act as generic utility bags
- When a service method updates multiple aggregates or persistence records that must stay in sync, use an explicit transaction.

## Repository Rules

- Repositories own query intent and persistence orchestration that is more complex than a trivial model call.
- Use repositories when code needs:
  - shared lookup constraints
  - repeated ordering rules
  - eager-loading policies
  - reusable filtering/query composition
  - refresh-after-write consistency
- Avoid repositories that are thin pass-through wrappers around a single Eloquent call with no domain value.
- Repositories should not absorb business policy branches that belong in services.
- Repository method names should describe domain lookup intent, for example `findActiveByEmail` or `getOrderedPublicProviders`.

## Model Rules

- Models represent persistence-backed domain entities.
- Models should stay focused on:
  - relationships
  - casts
  - scopes
  - accessors/mutators
  - narrowly scoped domain helpers
- Do not put multi-step workflows, cross-aggregate orchestration, or request-aware logic in models.
- If a model helper starts depending on more than its own state and direct relations, move the logic to a service.
- Declare casts for enums, JSON payloads, encrypted fields, and dates explicitly.
- Keep fillable/guarded strategy deliberate and consistent. Do not leave mass assignment implicit.

## Resource Rules

- API resources own transport shaping only.
- Resources should:
  - expose stable response fields
  - hide secrets and internal-only data
  - normalize naming for frontend/API consumers
- Resources must not decide business rules or run extra queries opportunistically.
- If a resource needs conditional branches, the conditions should be simple and based on already-loaded data.

## Policy, Job, Listener, And Event Rules

- Policies own authorization decisions that are broader than a single route guard.
- Jobs own background execution, retries, and queue-safe workflow steps.
- Listeners react to domain or framework events and should remain focused on a single reaction.
- Events should represent something that already happened, not something that still needs a decision.
- Do not move synchronous business orchestration into events just to hide complexity.

## Query And Transaction Boundaries

- Query composition reused in multiple places belongs in repositories or local query scopes.
- Eager load relations intentionally to avoid N+1 query behavior in controllers, services, and resources.
- Use transactions for multi-write workflows that must succeed or fail together.
- Do not open transactions around slow external network calls unless the design truly requires it.
- If an external API call and local persistence must coordinate, persist local intent clearly and keep failure handling explicit.

## Folder And File Organization

- Place classes in the directory that matches the layer owning the behavior.
- Mirror namespaces to directory structure consistently.
- Group tests by owning layer:
  - feature/controller behavior in `tests/Feature/Controllers/...`
  - service behavior in `tests/Unit/Services/...`
  - repository behavior in `tests/Unit/Repositories/...`
  - resource shaping in `tests/Unit/Http/Resources/...` when isolated coverage is justified
- Create a new service, repository, request, or resource file when a class starts representing a distinct responsibility.
- Avoid dumping unrelated helpers into `Support` or `Traits` unless the reuse is real and stable.

## Reuse And Modularity Rules

- Prefer extracting stable domain rules into services or value objects over copy-pasting branches across controllers.
- Prefer composition over inheritance unless inheritance genuinely models a stable contract.
- Introduce interfaces when they represent a real boundary such as pluggable providers, external clients, or testable infrastructure seams.
- Do not create interfaces "just in case" for classes with only one concrete use and no boundary value.

## Architecture Review Checklist

- Controller logic stays thin and transport-focused.
- Business workflows live in services.
- Query orchestration lives in repositories or scopes.
- Models remain small and persistence-focused.
- Transactions exist where multi-write consistency matters.
- Files are placed in the layer that owns the behavior.
- Reuse is achieved through stable abstractions, not generic helper dumping.
