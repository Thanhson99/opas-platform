# Utility Scripts

This folder contains reusable helper scripts for:
- One-command local startup
- Git branch cleanup
- Puppeteer / Browser automation (Chromium, Chrome, Edge, Brave)
- Docker image update & container restart (n8n, Ollama, etc.)

Scripts are separated by platform:

- **macOS / Linux** → `.sh` (Bash)
- **Windows** → `.ps1` (PowerShell) or `.bat` (CMD)

All scripts belong inside the `scripts/` folder and can be reused across machines and projects.

---

## 📂 Folder Structure (example)

```text
scripts/
├── start-local.sh                # macOS/Linux: bootstrap and open local web
├── start-local.ps1               # Windows PowerShell: bootstrap and open local web
├── start-local.bat               # Windows CMD wrapper for start-local.ps1
├── git-clean-branches.sh          # macOS/Linux: clean Git branches
├── git-clean-branches.ps1         # Windows: clean Git branches
├── launch-browser-connector.sh    # macOS: Chromium + Puppeteer launcher
├── launch-browser-connector.bat   # Windows: Chrome/Edge/Brave launcher
├── update-images-and-restart.sh
├── update-images-and-restart.ps1
└── README.md
```

---

# 1. Local Startup Scripts

These scripts are the recommended way to run the project after cloning.

What they do:
- create missing `.env` files from `.env.example`
- create the shared Docker network if needed
- start Docker services with cache-friendly defaults
- install Laravel Composer dependencies only when missing
- build Laravel frontend assets only when missing
- generate the Laravel app key only when missing
- run Laravel migrations
- wait for the app to respond, then open the browser automatically

The terminal output stays minimal and progress-oriented. Commands do not print secret values.
Use `--fresh` if you explicitly want to rebuild containers and rerun the heavier bootstrap steps.

---

## 1.1 macOS / Linux — `start-local.sh`

**Type:** Bash  
**Location:** `scripts/start-local.sh`

### Usage

```bash
chmod +x scripts/start-local.sh
./scripts/start-local.sh
./scripts/start-local.sh --fresh
```

---

## 1.2 Windows PowerShell — `start-local.ps1`

**Type:** PowerShell  
**Location:** `scripts/start-local.ps1`

### Usage

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\start-local.ps1 --fresh
```

---

## 1.3 Windows CMD — `start-local.bat`

**Type:** CMD wrapper  
**Location:** `scripts/start-local.bat`

### Usage

```bat
scripts\start-local.bat
scripts\start-local.bat --fresh
```

---

# 2. Git Branch Cleanup Scripts

These scripts help maintain a clean Git workspace by:

- Detecting the default branch (`main`, `master`, or remote HEAD)
- Running `git fetch --prune` to clean stale remote-tracking branches
- Removing local branches already merged into the default branch
- Removing local branches whose remote no longer exists
- Never deleting the default branch itself

> Run these scripts **inside a Git repository**.

---

## 2.1 macOS / Linux — `git-clean-branches.sh`

**Type:** Bash  
**Location:** `scripts/git-clean-branches.sh`

### Requirements

- Git  
- Bash shell

### Make executable (first time)

```bash
chmod +x scripts/git-clean-branches.sh
```

### Usage

```bash
scripts/git-clean-branches.sh
scripts/git-clean-branches.sh upstream
```

### Optional: Add to PATH

```bash
export PATH="$HOME/scripts:$PATH"
source ~/.zshrc
```

---

## 2.2 Windows — `git-clean-branches.ps1`

**Type:** PowerShell  
**Location:** `scripts/git-clean-branches.ps1`

### First-time setup

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
```

### Usage

```powershell
pwsh .\scripts\git-clean-branches.ps1
pwsh .\scripts\git-clean-branches.ps1 upstream
```

---

# 3. Puppeteer / Browser Connector Scripts

Scripts for launching Chromium/Chrome/Edge/Brave with remote debugging enabled.

---

## 3.1 macOS — `launch-browser-connector.sh`

**Type:** Bash  
**Location:** `scripts/launch-browser-connector.sh`

### Features

- Installs Chromium (Homebrew)
- Picks free port
- Creates automation profile
- Enables DevTools remote debugging
- Auto-installs `puppeteer-core`
- Runs a Node.js Puppeteer test

### Usage

```bash
chmod +x scripts/launch-browser-connector.sh
scripts/launch-browser-connector.sh
```

---

## 3.2 Windows — `launch-browser-connector.bat`

**Type:** Batch  
**Location:** `scripts/launch-browser-connector.bat`

### Usage

```bat
launch-browser-connector.bat
```

### Examples

```bat
launch-browser-connector.bat --browser edge --port 9333
launch-browser-connector.bat --use-system-profile
launch-browser-connector.bat --browser brave --headless
```

---

# 4. Docker Image Update & Restart Scripts

Scripts for pulling the latest Docker images and restarting containers,
working whether containers are running or stopped.

---

## 4.1 macOS / Linux — `update-images-and-restart.sh`

**Type:** Bash  
**Location:** `scripts/update-images-and-restart.sh`

### Features

- Pulls latest images from registry
- Recreates containers to apply updates
- Safe to run multiple times
- Keeps volumes and persistent data intact

### Usage

```bat
chmod +x scripts/update-images-and-restart.sh
scripts/update-images-and-restart.sh
```

---

## 4.2 Windows — `update-images-and-restart.ps1`

**Type:** PowerShell  
**Location:** `scripts/update-images-and-restart.ps1`

### First-time setup

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
```

### Usage

```powershell
.\scripts\update-images-and-restart.ps1
```

---
# 5. Best Practices

- Use consistent naming (`xxx.sh`, `xxx.ps1`, `xxx.bat`)
- One responsibility per script
- Avoid hard-coding environment-specific paths
- Add usage instructions at top of each script
- Keep this README updated when adding new utilities

---

# 6. Platform Summary

| Platform      | Script Type     | Extensions |
|---------------|-----------------|------------|
| macOS/Linux   | Bash            | `.sh`      |
| Windows       | PowerShell      | `.ps1`     |
| Windows       | CMD Batch       | `.bat`     |

---

# End of README
