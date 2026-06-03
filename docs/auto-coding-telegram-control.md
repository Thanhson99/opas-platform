# Auto-Coding Telegram Control

## Purpose

This document explains the current Telegram remote-control flow for the local auto-coding system.

Use it when you need to:
- configure the Telegram bot
- manage Telegram bot permissions from the Laravel admin
- expose the webhook locally through ngrok
- understand the current bot commands
- understand the current Telegram menus and smart dashboard behavior

## Current Scope

The Telegram bot is a remote interface for the connected local machine.

It currently supports:
- direct chat-session mode for remote coding
- queue inspection and management
- changed-file reporting with per-file change notes when available
- current-session chat cleanup
- all tracked bot-message cleanup
- stop/delete controls for queued work

## Setup Flow

### Provider mode

For Telegram tasks to run through Codex CLI on the connected machine, configure:

```env
AUTO_CODING_DEFAULT_REPOSITORY_PATH=/path/to/repository
AUTO_CODING_CONTAINER_REPOSITORY_PATH=/workspace/repo
AUTO_CODING_PROMPT_PATH=/path/to/repository/ai-local/agents/laravel-n8n-orchestrator.md
AUTO_CODING_PROVIDER=codex
AUTO_CODING_CODEX_EXECUTABLE=codex
AUTO_CODING_CODEX_APPROVAL_MODE=auto-edit
AUTO_CODING_CODEX_SANDBOX=workspace-write
AUTO_CODING_CODEX_TIMEOUT_SECONDS=900
AUTO_CODING_CODEX_EXEC_ARGS="--color never --skip-git-repo-check"
```

`auto-edit` maps to Codex CLI non-interactive execution with approval policy `never` inside the configured sandbox. Use `full-auto` only for an externally sandboxed machine because it bypasses Codex approvals and sandboxing.

When Laravel runs in Docker but Codex CLI runs on the host machine, also configure:

```env
AUTO_CODING_HOST_DB_HOST=127.0.0.1
AUTO_CODING_HOST_DB_PORT=5432
AUTO_CODING_LOCAL_WORKER_REPOSITORY_PATH=/path/to/repository
AUTO_CODING_LOCAL_WORKER_PROMPT_PATH=/path/to/repository/ai-local/agents/laravel-n8n-orchestrator.md
```

For a server deployment, change only these `.env` paths/hosts to match the server:
- `AUTO_CODING_DEFAULT_REPOSITORY_PATH`: repository path visible to the worker
- `AUTO_CODING_CONTAINER_REPOSITORY_PATH`: repository mount path inside the app container, if using Docker
- `AUTO_CODING_PROMPT_PATH`: absolute prompt file path on that runtime
- `AUTO_CODING_CODEX_EXECUTABLE`: `codex` or an absolute Codex CLI path
- `AUTO_CODING_HOST_DB_HOST` and `AUTO_CODING_HOST_DB_PORT`: DB endpoint visible from a host-run Codex worker

Telegram bot runtime config is database-first. Bot token, webhook secret, allowed chat IDs, allowed user IDs, allowed actions, locale, API base URL, and chat-history limits are managed in `telegram_bot_configs` through the admin screen.

For fresh deploys where you want to bootstrap that database row from `.env`, set:

```env
AUTO_CODING_TELEGRAM_BOT_TOKEN=1234567890:real-token
AUTO_CODING_TELEGRAM_WEBHOOK_SECRET=real-secret
AUTO_CODING_TELEGRAM_ALLOWED_CHAT_IDS=123456789
AUTO_CODING_TELEGRAM_ALLOWED_USER_IDS=123456789
AUTO_CODING_TELEGRAM_ALLOWED_ACTIONS=help,menu,conversation,chat_start,chat_ping,chat_status,chat_stop,chat_reset,create_task,queue,changes,summary,status,cancel_task,cancel_tasks,delete_task,delete_tasks,reset,resume
```

Then run:

```bash
php artisan opas:auto-coding:telegram:default-bot:import-env
```

After import, runtime still reads from the database. The env values are only the deployment/bootstrap source.

### 1. Configure the default bot in admin

Telegram bot runtime is now database-backed.

Configure the default bot from:

```text
/admin/auto-coding/telegram-bots
```

That admin screen owns:
- bot purpose, environment, and machine-group classification
- allowed chat IDs
- allowed user IDs
- allowed actions
- locale
- bot token and webhook secret
- runtime limits and allowed update types
- which bot is the default runtime bot
- runtime inspection, webhook register/delete, and command sync actions for the active default bot
- recent audit activity for config changes and runtime operations

The first admin load creates a blank `default` bot row automatically if it does not exist yet.

### 2. Start the local Telegram bridge

Recommended local command:

```bash
scripts/setup-telegram-ngrok.sh
```

This script:
- starts ngrok
- starts the auto-coding worker; Codex provider runs on the host when configured, other providers can use the Docker worker
- registers the Telegram webhook
- syncs Telegram bot commands
- persists the selected Telegram locale

Webhook registration and command sync now read from the default bot stored in the database.

### 3. Start in Telegram

After setup, open the bot and send:

```text
/start
```

Use `/help` when you want the dashboard/help view instead of entering direct chat mode.

## Commands

Current Telegram commands:

- `/start`: start direct Codex chat mode on the connected machine
- `/chat_status`: show the current direct chat session state
- `/stop`: stop direct Codex chat mode
- `/chat_reset`: clear the current direct chat context and start fresh
- `/queue [pending|running|blocked|failed|completed]`: inspect the latest tasks or one status slice
- `/changes [task-id|latest]`: show changed files
- `/cancel [task-id|latest:running]`: cancel one task
- `/cancel_all`: cancel all active tasks
- `/delete [task-id|latest:pending]`: permanently delete one pending task from the database
- `/delete_all`: delete all pending tasks
- `/clear [--force]`: remove tracked bot messages from the current chat session
- `/clear_all [--force]`: remove all tracked bot messages from oldest to newest

Compatibility notes:
- plain text remains the preferred way to create remote coding work while chat mode is active
- older internal/report commands may still be accepted for existing callbacks and tests, but they are no longer advertised in the default Telegram command list
- Telegram can only delete messages the bot can address by tracked message id and within Telegram Bot API deletion limits

## Conversational Chat

Telegram now also accepts plain text chat, not only slash commands.

There is now an explicit direct chat-session layer for this behavior:
- use `/start` when you want Telegram messages to behave like a remote Codex chat on the connected machine
- use `/chat_status` to inspect the current chat session, machine, and linked active task
- use `/chat_reset` to drop the current direct chat context without leaving chat mode
- use `/stop` when you want to go back to command-only control
- while chat mode is active, new coding tasks are tagged with `chat_session` transport metadata so reports and history keep that operator context
- with `AUTO_CODING_PROVIDER=codex`, those tasks are dispatched to `codex exec` on the connected machine and Telegram receives queue/progress/completion reports

Current conversational behavior:
- plain text defaults to a coding task when it looks like a work request
- `issue OPAS-0069 ...` and `github issue OPAS-0069 ...` create coding tasks with the issue key attached
- when an issue-linked task already exists locally, issue-only requests such as `/issue OPAS-0069` can reuse the latest issue summary and attach reusable `issue_context` for the provider
- when one issue has multiple conflicting local histories for the same task type, Telegram now asks which source task context should be reused instead of guessing silently
- that issue-context clarification can be completed either by tapping the suggested button or by replying with the source task id in chat
- the queued-task acknowledgement now also shows the reused source task, provider hint, scope hint, and workspace policy when they were inherited from local issue history
- `review ...` maps to a review task
- `validate ...`, `check ...`, `test ...`, `lint ...` map to a validation task
- `status`, `summary`, `changes`, `github`, `queue` map to the latest report action
- `check github`, `xem github`, `check pr`, `xem pr`, `check ci`, and `check status` also map to the latest report action
- plain-text reports can also target a task id, a status slice, an issue key, a branch, or a PR number such as `status 12`, `summary failed`, `github issue OPAS-0069`, `github branch feature/opas-0069`, `check pr 105`, or `queue blocked`
- ambiguous requests such as `làm tiếp`, `continue`, `fix this` trigger a clarification prompt instead of creating a task immediately
- dangerous actions such as cancel, delete, and deleteall require an explicit confirmation step before they run
- prefer `--force` when you intentionally want the destructive all-scope variant, for example `/delete_all --force`
- if the current chat has an active blocked task, the next plain text reply is treated as a follow-up resume response unless the message clearly looks like a standalone lookup

Examples:

```text
Fix Telegram progress reporting for blocked tasks
issue OPAS-0069 Fix Telegram GitHub report formatting
review the latest queue cleanup changes
validate apps/laravel Telegram webhook flow
check status
check github
github issue OPAS-0069
github branch feature/opas-0069
check pr 105
queue blocked
allow
```

## Menus

### Root Dashboard

`/start` renders a compact control dashboard.

It currently shows:
- worker snapshot
- activity snapshot
- blocked or failed tasks that need attention
- current task list

The root keyboard keeps only the core actions:
- `Start Chat`
- `Queue`
- `Latest Changes`
- `Clear Chat`
- `Clear All Chat`
- when a task is running, `Cancel Latest Running`

## GitHub Snapshot

`/github` and the matching task keyboard now return a chat-optimized GitHub snapshot.

It currently includes:
- issue key
- repository slug
- branch
- compare URL
- pull request status and URL when available
- CI status plus check summary when available
- a short headline
- the main blockers
- the recommended next GitHub action

Current limitation:
- this is still local-read and persisted-report driven
- it does not yet create, update, or merge PRs through the GitHub API

### Queue Management

The queue menu includes:
- all latest tasks
- status filters for pending, running, and blocked work
- cancel latest running
- cancel all active
- delete latest pending
- delete all pending

### Chat Cleanup

`/clear` deletes tracked bot messages from the current chat session. If no chat session is active, it falls back to the tracked recent bot messages.

`/clear_all` deletes all tracked bot messages from oldest to newest.

Both commands only operate on tracked message ids. They cannot guarantee deletion of untracked history or messages Telegram no longer allows the bot to delete.

## Callback Behavior

Inline button callbacks now acknowledge user interaction immediately with a short status line such as:
- `Opening menu...`
- `Loading queue...`
- `Refreshing commands...`
- `Cleaning chat...`

The message body then follows with the full result.

## Resume Flow

Blocked tasks can currently be resumed through:
- confirmation buttons
- single-question option buttons
- structured `/resume` text
- plain text replies when the chat is currently linked to a blocked task

Example:

```text
/resume 12 target_scope=services; target_file=apps/laravel/app/Services/...
```

## Local Operator Notes

- The bot only responds for allowed chat IDs and user IDs.
- Action execution is allow-listed by the default Telegram bot row stored in the database.
- The worker must be alive for queued tasks to progress beyond `pending`.
- The webhook must stay publicly reachable while testing locally.

## Recommended Operator Flow

For a new user:

1. run `scripts/setup-telegram-ngrok.sh`
2. send `/start`
3. send normal Telegram messages to queue work for the connected machine
4. use `Queue` to inspect, stop, or delete queued work
5. use `Latest Changes` to inspect changed files
6. send `/stop` when you want to leave direct chat mode
7. use `Clear Chat` at the end of a session, or `Clear All Chat` for full tracked cleanup
