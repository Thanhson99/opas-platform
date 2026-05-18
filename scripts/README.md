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
├── start-local.sh                # macOS/Linux: bootstrap full Docker stack
├── start-local-lite.sh           # macOS/Linux: run only Laravel + Vite locally
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

## 0.1 macOS / Linux - `start-local.sh`

Docker-oriented startup for the full local stack. This is the main script if your `.env` uses
container hosts like `postgres`.

```bash
chmod +x scripts/start-local.sh
scripts/start-local.sh
scripts/start-local.sh --fresh
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
