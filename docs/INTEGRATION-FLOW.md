# Integration Flow

## 1. Laravel -> n8n

Use this when:

- you need to trigger long-running workflows
- you need retries
- you need step-by-step logging
- you need to combine AI, translation, uploads, or scheduling

Recommended approach:

- Laravel calls an n8n webhook
- Laravel stores `job_id`, `workflow_name`, `payload_hash`, and `status`
- n8n returns a short result immediately or calls Laravel back later

## 2. Laravel -> Python Services

Use this when:

- you need a fast response
- you need a focused utility service
- you need parsing, scraping, or preprocessing

Current status:

- `apps/laravel/app/Services/Python/PythonService.php` is currently a basic client
- it should evolve into domain-specific services instead of one generic class for every use case

## 3. n8n -> Ollama

Use this when:

- drafting content
- rewriting
- reviewing output
- generating structured JSON

Recommended conventions:

- `qwen2.5:7b`: writer / planner
- `mistral:7b`: critic / reviewer
- each workflow should store its source prompt in `ai-local/agents/*.md`
- n8n should reference prompts instead of embedding long prompts directly in nodes whenever possible

## 4. n8n -> LibreTranslate

Use this when:

- translating drafts
- generating a second language version
- preprocessing or postprocessing AI content

Recommended approach:

- perform direct translation with LibreTranslate
- if quality slips, add one AI post-edit step with Ollama

## 5. Python Services -> Ollama

Use this when:

- AI processing needs more programming control
- JSON output must be validated
- you need chunking, retries, or fallback logic

In that case, the Python service should act as an adapter:

- receive payloads from Laravel or n8n
- call Ollama
- validate the schema
- return normalized output

## Suggested Flow For This Stack

1. The user submits a request in Laravel.
2. Laravel triggers an n8n workflow.
3. n8n calls a Python service to fetch or clean data.
4. n8n calls Qwen to generate a draft.
5. n8n calls LibreTranslate if another language version is needed.
6. n8n calls Mistral to review or post-edit the result.
7. n8n returns the result to Laravel.
8. Laravel displays the result and stores logs and history.
