# Local AI Runbook

## Components

- Ollama container: `ollama`
- Writer model: `qwen2.5:7b`
- Reviewer model: `mistral:7b`
- Translation service: `libretranslate`

## Commands To Remember

Start the stack:

```bash
docker compose up -d
```

Pull Ollama models:

```bash
bash scripts/ollama-pull-models.sh
```

Check installed models:

```bash
docker exec -it ollama ollama list
```

## Model Usage Conventions

### `qwen2.5:7b`

Use for:

- drafting
- synthesis
- structure planning
- JSON plan generation

### `mistral:7b`

Use for:

- review
- logic issue detection
- compression
- output normalization

## Prompt Conventions

- Store each source prompt in `ai-local/agents/`.
- Every prompt should clearly define:
  - role
  - input
  - constraints
  - output format
- Prefer JSON output when the workflow needs downstream processing.

## How To Use In n8n

1. Create an HTTP Request node to `http://ollama:11434/api/chat`.
2. Choose the model based on the prompt file.
3. Load the system and user content from `.md` files in the repository.
4. Always validate the output before writing to the database or publishing.

## How To Use In Laravel/Python

- Laravel should call n8n for long-running workflows.
- Python services should call Ollama when validation, retry logic, or chunking is needed.
- Do not spread business prompts across source code without a canonical documentation file.
