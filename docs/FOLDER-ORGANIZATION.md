# Folder Organization

## Principles

- Do not move runtime folders that are already mounted by `docker-compose.yml`.
- Add documentation and prompt folders so operational flows stay explicit and maintainable.
- Each service should own its own runtime code, while shared documentation stays at the repository root.

## Recommended Structure

```text
apps/laravel/        Web app and API gateway
services/python/     FastAPI services
services/n8n/        Workflow, node, and credential data
services/libretranslate/
  Dockerfile + ArgosTranslate models
docker/              Persistent runtime data
nginx/               Reverse proxy
scripts/             Utility scripts
docs/                Architecture, runbooks, and integration guides
ai-local/            Prompts and local LLM control conventions
```

## Meaning Of The Added Folders

### `docs/`

Documentation for operators and developers:

- architecture
- integration flow
- folder conventions
- local AI runbook

### `ai-local/`

Prompt pack and control rules for consistent local AI usage:

- drafting prompts
- review prompts
- orchestration prompts
- translation post-edit prompts

## Priority Order When Adding New Files

1. Runtime files should live inside the relevant service.
2. Prompt or instruction files should live in `ai-local/`.
3. Flow descriptions or conventions should live in `docs/`.
4. Support scripts should live in `scripts/`.

## What To Avoid

- Do not scatter AI prompts across `n8n/workflows/` or `laravel/resources/`.
- Do not put operational documentation only inside framework-specific default READMEs.
- Do not add more runtime folders unless all related volume mounts and scripts are updated together.
