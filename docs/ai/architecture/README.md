# Enterprise Architecture

## Monorepo Boundaries

```text
apps/laravel/
  app/                 Laravel backend application code
  routes/              Laravel route declarations
  resources/js/spa/    React SPA source
  resources/scss/      SPA and Laravel styles
  tests/               Laravel/PHP tests
docs/                  Repository documentation and coding rules
scripts/               Operational scripts
```

## Frontend Flow

```text
Router -> Page -> Layout -> Feature Components -> Shared UI -> Hooks -> Services -> API Client -> Utils
```

## Backend Flow

```text
Route -> Controller -> Request/Resource -> Service -> Repository -> Model
```

## Data Flow

```text
React UI -> Feature Hook -> API Service -> HTTP Client -> Laravel Controller -> Service -> Repository -> Model -> Resource -> API Service Mapper -> React UI
```

## Hard Boundaries

- Use feature-based organization for large frontend and backend areas.
- Do not organize large app code only by technical type.
- Keep UI, domain logic, API access, state orchestration, and layout concerns separated.
- Pages are composition boundaries only.
- Layouts are shell boundaries only.
- Components render UI only.
- Hooks orchestrate frontend stateful logic.
- Services own domain workflows or API communication.
- Repositories own reusable database query intent.
- API clients own transport and response normalization.
- Never import page modules from components, hooks, services, or API clients.
- Never import feature modules from shared components.
- Never let layout components depend on page-specific state or page-specific API contracts.
- Never put business rules in JSX.
- Never put SQL or database orchestration in controllers.

## Recommended React Structure

```text
resources/js/spa/
  app/
  api/
  components/
  features/
    feature-name/
      api/
      components/
      hooks/
      pages/
      types/
      utils/
  hooks/
  layouts/
  styles/
  utils/
```
