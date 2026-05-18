# System Architecture

## Goal

This repository is organized around the following model:

- Laravel = user-facing interface and entry point
- n8n = workflow engine
- Python services = utility layer and data processing
- Ollama = local LLM runtime
- LibreTranslate = local translation layer
- PostgreSQL = shared data store

## Logical Diagram

```text
User
  |
  v
Laravel UI/API
  | \
  |  \--> Python Services
  |
  \----> n8n Workflows
             | \
             |  \--> LibreTranslate
             |
             \----> Ollama
```

## Integration Principles

- Laravel should not call too many AI nodes directly.
- n8n should keep the orchestration role for long pipelines.
- Python services should own tasks such as:
  - parsing
  - scraping
  - preprocessing
  - validation
  - external API adapter logic
- Ollama should be called through structured prompts and JSON output whenever possible.
- LibreTranslate should stay in the translation layer and should not absorb business logic.

## Current Source Status

- Laravel already has a Python client service: `App\Services\Python\PythonService`
- Laravel routes currently focus mainly on:
  - coin
  - stock
  - video automation
- The Python service currently exposes only a small set of aggregate routes; more business-oriented routes should be added if Laravel is expected to depend on it more heavily.
- n8n already contains multiple workflow JSON files, but the use-case mapping is not yet documented clearly.

## Recommended Next Direction

Use cases should be split into three layers:

1. `interactive`
   Laravel calls a Python service or n8n webhook directly and waits for an immediate response.
2. `workflow`
   n8n handles long-running pipelines and job state.
3. `ai-assisted`
   n8n or Python calls Ollama and LibreTranslate, then returns normalized output.
