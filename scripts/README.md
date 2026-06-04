# Utility Scripts

This folder contains reusable helper scripts for:
- one-command local startup
- Git branch cleanup
- Puppeteer / browser automation (Chromium, Chrome, Edge, Brave)
- Docker image update & container restart

Scripts are separated by platform:

- **macOS / Linux** -> `.sh` (Bash)
- **Windows** -> `.ps1` (PowerShell) or `.bat` (CMD)

---

## Folder Structure

```text
scripts/
├── setup-telegram-webhook.bat    # Windows CMD wrapper: inspect/register/delete Telegram webhook
├── setup-telegram-webhook.ps1    # Windows PowerShell: inspect/register/delete Telegram webhook
├── setup-telegram-ngrok.bat      # Windows CMD wrapper: launch ngrok and register Telegram webhook
├── setup-telegram-ngrok.ps1      # Windows PowerShell: launch ngrok and register Telegram webhook
├── setup-telegram-ngrok.sh       # macOS/Linux: launch ngrok and register Telegram webhook
├── setup-telegram-webhook.sh     # macOS/Linux: inspect/register/delete Telegram webhook
├── start-local.sh                # macOS/Linux: bootstrap full Docker stack
├── start-local-lite.sh           # macOS/Linux: run only Laravel + Vite locally
├── test-laravel.sh               # macOS/Linux: run Laravel checks separately from startup
├── start-local.ps1               # Windows PowerShell: bootstrap full Docker stack
├── start-local.bat               # Windows CMD wrapper for start-local.ps1
├── git-clean-branches.sh
├── git-clean-branches.ps1
├── launch-browser-connector.sh
├── launch-browser-connector.bat
├── run-laravel-queue.sh
├── update-images-and-restart.sh
├── update-images-and-restart.ps1
└── README.md
```

---

# 0. Local App Start Scripts

These are the recommended ways to boot OPAS locally after cloning.

What they cover:
- full Docker stack startup
- lightweight Laravel + React startup
- environment bootstrap from `.env.example`
- Laravel key generation and dependency install when required
- frontend asset build when required
- migrations and readiness checks
- clear follow-up verification commands for PHP and React
- Telegram remote-control bootstrap for the auto-coding worker

## 0.1 macOS / Linux - `start-local.sh`

Docker-oriented startup for the full local stack. By default it starts PostgreSQL, Laravel,
nginx, n8n, Ollama, Python services, and LibreTranslate.

Use `--core` only when you intentionally want PostgreSQL, Laravel, and nginx without automation services.

```bash
chmod +x scripts/start-local.sh
scripts/start-local.sh
scripts/start-local.sh --fresh
scripts/start-local.sh --core
```

## 0.2 macOS / Linux - `start-local-lite.sh`

Lightweight startup for running only Laravel + Vite outside Docker.

Use this only when you do not want to boot the full Docker stack.
Typical use cases:
- frontend/layout work with React SPA
- quick API debugging on local PHP/Node
- machines where you only want `apps/laravel` to run

```bash
chmod +x scripts/start-local-lite.sh
scripts/start-local-lite.sh
```

## 0.3 Windows PowerShell - `start-local.ps1`

Windows PowerShell version of the full Docker stack startup.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\start-local.ps1 --fresh
```

## 0.4 Windows CMD - `start-local.bat`

CMD wrapper that forwards to `start-local.ps1`.

```bat
scripts\start-local.bat
scripts\start-local.bat --fresh
```

## 0.5 Quality / CI commands after local boot

Startup scripts only boot services. Use the Laravel test script when you want verification.

```bash
chmod +x scripts/test-laravel.sh
scripts/test-laravel.sh
scripts/test-laravel.sh --with-frontend
scripts/test-laravel.sh --docker --ci
```

Run these inside `apps/laravel`:

```bash
composer check   # php artisan test + pint --test + phpstan
npm run check    # eslint + prettier check
npm run ci       # frontend check + production build
composer ci      # backend checks + frontend checks + production build
```

## 0.6 macOS / Linux - `run-laravel-queue.sh`

Run the Laravel queue worker when queued emails such as verify-email or reset-password messages need to be processed locally.

```bash
chmod +x scripts/run-laravel-queue.sh
scripts/run-laravel-queue.sh
scripts/run-laravel-queue.sh --docker
scripts/run-laravel-queue.sh --local
```

Behavior:
- `auto`: default, detects whether Laravel is configured for Docker-style hosts such as `postgres`
- `--docker`: always runs `php artisan queue:work` inside the `laravel` container
- `--local`: always runs `php artisan queue:work` on the local machine

## 0.7 macOS / Linux - `setup-telegram-webhook.sh`

Inspect, register, delete, and sync Telegram bot commands for the auto-coding remote-control flow.

Use this after filling the Telegram section in `apps/laravel/.env` and after exposing your local app through a public HTTPS URL such as ngrok or Cloudflare Tunnel.

```bash
chmod +x scripts/setup-telegram-webhook.sh

# Open interactive menu
scripts/setup-telegram-webhook.sh

# Inspect current Telegram webhook state
scripts/setup-telegram-webhook.sh --inspect

# Register a webhook using a public base URL
scripts/setup-telegram-webhook.sh https://abc123.ngrok-free.app

# Register and tell Telegram to drop queued updates
scripts/setup-telegram-webhook.sh https://abc123.ngrok-free.app --drop-pending-updates

# Delete the webhook
scripts/setup-telegram-webhook.sh --delete

# Sync Telegram bot commands only
scripts/setup-telegram-webhook.sh --sync-commands
```

Behavior:
- validates required Telegram env vars before registration or delete
- clears Laravel config cache by default before calling artisan
- builds the final webhook route as `/api/telegram/auto-coding/webhook`
- syncs Telegram bot commands automatically after successful registration
- when started without arguments, shows an interactive menu for register / inspect / delete / sync

## 0.8 macOS / Linux - `setup-telegram-ngrok.sh`

One-command helper for local Telegram testing with ngrok.

This script:
- verifies the Docker/Laravel runtime before opening any public tunnel
- imports Telegram `.env` bootstrap values into the database-backed default bot when present
- validates the active database-backed bot runtime before opening public webhook traffic
- starts an ngrok tunnel for the Laravel app port
- starts the auto-coding worker; `AUTO_CODING_PROVIDER=codex` runs it on the host so it can use the host Codex CLI
- waits for a public HTTPS URL
- calls `setup-telegram-webhook.sh`
- registers the Telegram webhook automatically
- syncs Telegram bot commands automatically

```bash
chmod +x scripts/setup-telegram-ngrok.sh

# Open interactive menu
scripts/setup-telegram-ngrok.sh

# Start in Vietnamese mode
scripts/setup-telegram-ngrok.sh start --lang=vi

# Start ngrok for the default Laravel port 8881 and register webhook
scripts/setup-telegram-ngrok.sh start --port=8881

# Start ngrok for a custom local Laravel port
scripts/setup-telegram-ngrok.sh start --port=8000

# Start ngrok and drop queued Telegram updates while registering webhook
scripts/setup-telegram-ngrok.sh start --drop-pending-updates

# Check tunnel status
scripts/setup-telegram-ngrok.sh status

# Stop the background ngrok tunnel
scripts/setup-telegram-ngrok.sh stop
```

Requirements:
- `ngrok` installed and available in `PATH`
- ngrok auth already configured on your machine
- Docker Laravel service running when your app is using the Docker stack
- Laravel app reachable on the local port you pass
- Telegram env already configured in `apps/laravel/.env`

Fail-fast behavior:
- when Docker is required but the `laravel` service is stopped, the script exits before opening ngrok
- when the Laravel app is not reachable on the selected local port, the script exits before opening ngrok

Language:
- `--lang=en` keeps Telegram bot copy in English
- `--lang=vi` switches Telegram bot copy and onboarding help to Vietnamese
- when started without arguments, the interactive menu asks for `EN` or `VI` and persists the selection to `apps/laravel/.env`

After a successful start:
- the script shows only loading steps in the terminal and avoids printing sensitive Telegram details
- the selected Telegram locale is persisted so the bot replies in the same language
- the `/help` dashboard is intended for new operators and includes:
  - worker snapshot
  - activity snapshot
  - tasks that need attention such as blocked or failed work
  - quick actions that adapt to blocked, failed, or running tasks
  - smart home actions such as `Resume Latest Blocked`, `Next Action`, `Follow-up`, `Latest Failed`, or `Latest Running`
- for a new user, the recommended Telegram flow is:
  - `/start` to enable direct Codex chat mode
  - `/queue` to see active tasks
  - `/changes latest` to review changed files
  - `/clear` or `/clear_all` when the chat needs cleanup
  - `/stop` to leave direct Codex chat mode

Current Telegram command set:
- `/start`
- `/stop`
- `/chat_status`
- `/chat_reset`
- `/queue [pending|running|blocked|failed|completed]`
- `/changes [task-id|latest]`
- `/cancel [task-id|latest:running]`
- `/cancel_all`
- `/delete [task-id|latest:pending]`
- `/delete_all`
- `/clear [--force]`
- `/clear_all [--force]`

Telegram menu structure:
- `Start Chat`: enter direct Codex chat mode
- `Queue`: inspect and manage queued work
- `Latest Changes`: inspect changed files
- `Clear Chat` / `Clear All Chat`: clean tracked bot messages

For more detail, see [docs/auto-coding-telegram-control.md](../docs/auto-coding-telegram-control.md).

Notes:
- On macOS/Linux, the script now attempts to install `ngrok` automatically using `brew`, `apt-get`, or `snap` when missing.
- On Windows, the PowerShell script attempts to install `ngrok` automatically using `winget` first, then `choco`.
- `stop` now stops both the ngrok tunnel and the background auto-coding worker process.
- when started without arguments, shows an interactive menu for start / status / stop

## 0.9 Windows - `setup-telegram-ngrok.ps1` / `setup-telegram-ngrok.bat`

Windows versions of the one-command Telegram tunnel + webhook setup flow.

The PowerShell flow uses the same database-backed Telegram bot config as macOS/Linux. It also supports `AUTO_CODING_PROVIDER=codex` by running the auto-coding worker on the Windows host so it can use the host Codex CLI.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-telegram-ngrok.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\setup-telegram-ngrok.ps1 start --lang=vi
powershell -ExecutionPolicy Bypass -File .\scripts\setup-telegram-ngrok.ps1 status
powershell -ExecutionPolicy Bypass -File .\scripts\setup-telegram-ngrok.ps1 stop
```

```bat
scripts\setup-telegram-ngrok.bat
scripts\setup-telegram-ngrok.bat status
scripts\setup-telegram-ngrok.bat stop
```

## 0.10 Windows - `setup-telegram-webhook.ps1` / `setup-telegram-webhook.bat`

Windows versions of the Telegram webhook management flow.

When the Laravel `.env` points at a Docker database host such as `postgres`, the PowerShell webhook script runs artisan through `docker compose exec -T laravel`, so Windows machines do not need host PHP just to inspect, register, delete, or sync Telegram webhook state.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-telegram-webhook.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\setup-telegram-webhook.ps1 --inspect
powershell -ExecutionPolicy Bypass -File .\scripts\setup-telegram-webhook.ps1 --delete
```

```bat
scripts\setup-telegram-webhook.bat
scripts\setup-telegram-webhook.bat --inspect
scripts\setup-telegram-webhook.bat --delete
```

---

# 1. Git Branch Cleanup Scripts

These scripts help maintain a clean Git workspace by:
- detecting the default branch (`main`, `master`, or remote HEAD)
- running `git fetch --prune`
- removing local branches already merged into the default branch
- removing local branches whose remote no longer exists
- never deleting the default branch itself

> Run these scripts inside a Git repository.

## 1.1 macOS / Linux - `git-clean-branches.sh`

```bash
chmod +x scripts/git-clean-branches.sh
scripts/git-clean-branches.sh
scripts/git-clean-branches.sh upstream
```

## 1.2 Windows - `git-clean-branches.ps1`

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
pwsh .\scripts\git-clean-branches.ps1
pwsh .\scripts\git-clean-branches.ps1 upstream
```

---

# 2. Puppeteer / Browser Connector Scripts

Scripts for launching Chromium/Chrome/Edge/Brave with remote debugging enabled.

## 2.1 macOS - `launch-browser-connector.sh`

Features:
- installs Chromium via Homebrew
- picks a free port
- creates an automation profile
- enables DevTools remote debugging
- auto-installs `puppeteer-core`
- runs a Node.js Puppeteer test

```bash
chmod +x scripts/launch-browser-connector.sh
scripts/launch-browser-connector.sh
```

## 2.2 Windows - `launch-browser-connector.bat`

```bat
launch-browser-connector.bat
launch-browser-connector.bat --browser edge --port 9333
launch-browser-connector.bat --use-system-profile
launch-browser-connector.bat --browser brave --headless
```

---

# 3. Docker Image Update & Restart Scripts

Scripts for pulling the latest Docker images and restarting containers safely.

## 3.1 macOS / Linux - `update-images-and-restart.sh`

```bash
chmod +x scripts/update-images-and-restart.sh
scripts/update-images-and-restart.sh
```

## 3.2 Windows - `update-images-and-restart.ps1`

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
.\scripts\update-images-and-restart.ps1
```

---

# 4. Best Practices

- use consistent naming (`xxx.sh`, `xxx.ps1`, `xxx.bat`)
- keep one responsibility per script
- avoid hard-coding environment-specific paths
- keep this README updated when adding new utilities
