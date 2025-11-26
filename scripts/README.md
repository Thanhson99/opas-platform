# Utility Scripts

This folder contains reusable helper scripts for:
- Git branch cleanup
- Puppeteer / Browser automation (Chromium, Chrome, Edge, Brave)

Scripts are separated by platform:

- **macOS / Linux** → `.sh` (Bash)
- **Windows** → `.ps1` (PowerShell) or `.bat` (CMD)

All scripts belong inside the `scripts/` folder and can be reused across machines and projects.

---

## 📂 Folder Structure (example)

```text
scripts/
├── git-clean-branches.sh          # macOS/Linux: clean Git branches
├── git-clean-branches.ps1         # Windows: clean Git branches
├── launch-browser-connector.sh    # macOS: Chromium + Puppeteer launcher
├── launch-browser-connector.bat   # Windows: Chrome/Edge/Brave launcher
└── README.md
```

---

# 1. Git Branch Cleanup Scripts

These scripts help maintain a clean Git workspace by:

- Detecting the default branch (`main`, `master`, or remote HEAD)
- Running `git fetch --prune` to clean stale remote-tracking branches
- Removing local branches already merged into the default branch
- Removing local branches whose remote no longer exists
- Never deleting the default branch itself

> Run these scripts **inside a Git repository**.

---

## 1.1 macOS / Linux — `git-clean-branches.sh`

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

## 1.2 Windows — `git-clean-branches.ps1`

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

# 2. Puppeteer / Browser Connector Scripts

Scripts for launching Chromium/Chrome/Edge/Brave with remote debugging enabled.

---

## 2.1 macOS — `launch-browser-connector.sh`

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

## 2.2 Windows — `launch-browser-connector.bat`

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

# 3. Best Practices

- Use consistent naming (`xxx.sh`, `xxx.ps1`, `xxx.bat`)
- One responsibility per script
- Add usage instructions at top of each script
- Keep this README updated whenever adding new scripts

---

# 4. Platform Summary

| Platform      | Script Type     | Extensions |
|---------------|-----------------|------------|
| macOS/Linux   | Bash            | `.sh`      |
| Windows       | PowerShell      | `.ps1`     |
| Windows       | CMD Batch       | `.bat`     |

---

# End of README
