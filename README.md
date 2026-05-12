```
             ██████╗ ██████╗  █████╗ ███████╗
            ██╔═══██╗██╔══██╗██╔══██╗██╔════╝
            ██║   ██║██████╔╝███████║███████╗
            ██║   ██║██╔═══╝ ██╔══██║╚════██║
            ╚██████╔╝██║     ██║  ██║███████║
             ╚═════╝ ╚═╝     ╚═╝  ╚═╝╚══════╝
 O N L I N E   P R O F I T   A U T O M A T I O N   S Y S T E M
                      ( O P A S )
```

![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?logo=docker&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-Framework-FF2D20?logo=laravel)
![n8n](https://img.shields.io/badge/n8n-Automation-EA4C89?logo=n8n)
![Python](https://img.shields.io/badge/Python-Microservices-3776AB?logo=python&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Database-4169E1?logo=postgresql)
![LibreTranslate](https://img.shields.io/badge/LibreTranslate-Local%20Translation-2AA876)
![AI](https://img.shields.io/badge/Local%20AI-Ollama-000000)
![CI/CD](https://img.shields.io/badge/GitHub%20Actions-CI%2FCD-2088FF?logo=githubactions)
![License](https://img.shields.io/badge/License-Custom-blue)

# Laravel + n8n Automation Lab (OPAS)

> A personal automation lab where **workflows become systems** — combining Laravel, n8n, Python microservices, PostgreSQL, and Docker to orchestrate content, data, and growth.  
> Built for today’s automation… but designed to scale into tomorrow’s “hands-off” operations.

---

## ✨ What is this repository?

This repo is my evolving **automation ecosystem** — a place to experiment, build, and harden real workflows into reliable pipelines.

It brings together:

- **Laravel** → Web UI, control panel, dashboards, job tracking  
- **n8n** → Automation engine (workflows, schedulers, connectors)  
- **Python services** → Helper APIs for parsing, enrichment, automation tasks  
- **PostgreSQL** → Shared data backbone (jobs, logs, content, dedupe, metadata)  
- **LibreTranslate** → Local translation service for **multi-language workflows** (not limited to Vietnamese)  
- **Local AI Runtime (Ollama)** → Offline-first LLM workflows (writing, rewriting, reviewing, SEO polishing)  
- **Docker** → Everything containerized, reproducible, portable  
- **GitHub Actions** → CI/CD and automation hygiene  
- **Cross-platform scripts** → macOS + Windows helpers

This is not a single-purpose tool. It’s a sandbox for building automation that can grow into production-grade systems:
- Content pipelines
- Multi-language publishing
- Data enrichment
- Notifications
- Business operations automation

---

## 🌍 Multilingual by design (not “just Vietnamese”)

LibreTranslate is included to enable **multilingual automation** at the infrastructure level:
- Translate content into multiple languages
- Local + private translation
- Easy to swap with paid providers later if needed

The goal is to treat language as a *first-class feature*, not an afterthought.

---

## 🤖 AI layer (local-first, future-ready)

This repo includes a Local AI runtime (Ollama) so you can build AI workflows without being locked into a paid API from day one.

Typical AI uses here:
- Rewrite RSS/news into more readable articles
- Generate SEO-friendly structure (headings, excerpt, meta description)
- Create “editor mode” review checklists
- Cross-review content with **two different models** (writer + critic)

Model strategy (inside one Ollama container):
- **Writer model**: `qwen2.5:7b`  
- **Critic/editor model**: `mistral:7b`  

You can later switch to any paid provider (OpenAI/Gemini/...) without redesigning your pipeline — the workflow is built around stable JSON outputs and modular “AI provider” nodes.

---

## 🧠 High-Level Architecture

```text
┌──────────────────────────┐
│        Users / UI        │
│   apps/laravel (Web UI)  │
└────────────┬─────────────┘
             │
             │ HTTP / internal calls
             ▼
┌──────────────────────────┐
│        NGINX Proxy       │
└───────┬────────┬─────────┘
        │        │
        │        │
        ▼        ▼
┌──────────────┐  ┌──────────────────────┐
│ services/n8n │  │ services/python      │
│ Workflows    │  │ Utility APIs         │
└──────┬───────┘  └──────────┬───────────┘
       │                     │
       ├──────────────┐      │
       ▼              ▼      ▼
┌──────────────┐  ┌──────────────┐
│ Ollama       │  │ LibreTranslate│
│ Local LLM    │  │ Translation   │
└──────────────┘  └──────────────┘

┌──────────────────────────┐
│       PostgreSQL         │
│ Shared application data  │
└──────────────────────────┘
```

---

## 🧰 Tech Stack

### **Backend / DevOps**
- ⚙️ Laravel (AdminLTE UI for now)
- 🤖 n8n (workflow automation)
- 🐍 Python microservices (FastAPI / Flask style)
- 🐘 PostgreSQL
- 🐳 Docker & Docker Compose
- 🌐 NGINX Reverse Proxy

### **Automation & Integrations**
- 📹 Multi-platform publishing (YouTube today, more later)
- 🧾 Content ingestion (RSS/news → enrichment → publish)
- 🌍 Translation pipelines (LibreTranslate)
- 🧠 Local AI writing/review pipelines (Ollama)
- 🔔 Notifications (Telegram/Email/etc. planned)
- 🔑 OAuth integrations

### **Tooling**
- 🧹 **Scripts for macOS + Windows (Git, Puppeteer, Browser Automation)**
- 📦 Local translation model support (Vietnamese)
- 🧪 GitHub Actions CI/CD

---

## 📂 Project Structure

```text
LARAVEL-N8N-AUTOMATION/
├── .github/                      # CI/CD pipelines
├── ai-local/                     # Local AI prompts and control docs
├── apps/
│   └── laravel/                  # Laravel application
├── docker/                       # Docker-related configs and persistent data
│   ├── ollama/                   # Ollama model storage (persistent)
│   └── postgres/
├── docs/                         # Architecture and runbooks
├── nginx/                        # nginx configuration
├── services/
│   ├── libretranslate/           # LibreTranslate service + Argos models
│   ├── n8n/                      # n8n data, credentials, workflows
│   └── python/                   # Python helper microservices
├── scripts/                      # Automation scripts (macOS + Windows)
├── videos/
├── videos_uploaded/
├── .gitignore
├── docker-compose.yml
└── README.md
```

### Notes on the new structure

- `apps/laravel/` replaces the old root `laravel/` folder
- `services/python/` replaces the old `python-services/` folder
- `services/n8n/` replaces the old root `n8n/` folder
- `services/libretranslate/` now contains both the LibreTranslate Docker setup and local Argos model packages

### Internal docs

- **Architecture Overview**: `docs/ARCHITECTURE.md`
- **Folder Organization**: `docs/FOLDER-ORGANIZATION.md`
- **Integration Flow**: `docs/INTEGRATION-FLOW.md`
- **Local AI Runbook**: `docs/LOCAL-AI-RUNBOOK.md`
- **AI Prompt Pack**: `ai-local/README.md`

---

## 🔁 Automation Workflows

### 🔥 Current / In Progress

#### 📹 Multi-Platform Video Upload Automation
- Auto-detect videos in mounted folders  
- Validate:
  - Missing folder  
  - Empty folder  
  - Unsupported file types  
- Upload to **YouTube** via OAuth2  
- Support metadata:
  - Title, Description, Privacy, Tags, Category, Region  
- Post-processing:
  - Move uploaded files → `videos_uploaded/`  
  - Log results to PostgreSQL  
- Works with **n8n Read Binary Files + YouTube Node**  
- Prepares for TikTok automation (next step)

#### 🗄 Database Pipelines
- n8n + PostgreSQL integration  
- DataTable-like pipelines  
- Sync jobs between Laravel ↔ n8n ↔ Python  
- Real-time job monitoring from UI (planned)

#### 🌍 Translation / NLP Workflows
- Local **LibreTranslate** instance (fast, private)
- Multi-language content workflows (not limited to Vietnamese)
- Can be swapped with paid translation APIs later
- Laravel + n8n endpoint wrappers
- Python microservices for advanced text processing  
  (e.g., cleanup, metadata extraction)

#### 🧠 AI-Assisted Content Pipelines (Local-first)
- RSS/news ingestion → normalization → dedupe
- Draft generation (writer model)
- Cross-review + improvements (critic model)
- SEO structuring (headings, excerpt, metadata)
- Safe fallback mode if AI is disabled

#### 🐍 Python Automation Layer
- Custom microservices called by n8n or Laravel
- Handles heavy tasks:
  - Data parsing
  - External API calls
  - Cron jobs  
- Future plan:
  - Trading bot engine  
  - AI-enhanced scraping  
  - Risk alert engine

#### 🧹 Dev Scripts (macOS + Windows)
- Git cleanup scripts:
  - remove merged branches
  - remove stale branches
- Browser automation scripts:
  - Launch Chromium/Chrome/Edge with remote debugging
  - Puppeteer testing environment
- Cross-platform compatibility

---

## 🧭 Roadmap

### 🚀 Short-Term
- RSS → WordPress publishing workflow (SEO + media handling)  
- System-wide logging & monitoring dashboard  
- Workflow triggers from Laravel UI  
- Integrated notification system (Telegram, Email, Zalo)

### 🔥 Mid-Term
- Trading bot foundation  
  - Signal collector  
  - Backtesting module  
  - Strategy runners (Python)  
- Dropshipping automation  
  - Product sync  
  - Auto-listing  
  - Price/stock automation  
- Affiliate automation (Alifate, others)

### 🌐 Long-Term
- Replace AdminLTE → Modern UI (Tailwind / Vue / React)
- Multi-tenant automation workspace
- Public API for workflow orchestration
- Analytics dashboards that explain “why the system did what it did”
- Pluggable AI providers (local + paid) as interchangeable modules

---

## 💻 Local Development Guide

### 1. Requirements
- Docker + Docker Compose
- Git
- Node.js (optional, for local testing)
- Python 3.x (optional)

### 2. Clone project
```bash
git clone https://github.com/<your-username>/laravel-n8n-automation.git
cd laravel-n8n-automation
```

### 3. Start all services
```bash
docker compose up -d
```

### 4. Accessing services
- **Laravel App** → http://localhost:8881  
- **n8n** → http://localhost:5678  
- **LibreTranslate** → http://localhost:8883  
- **Python Services** → http://localhost:8884  

### 5. Useful commands
```bash
docker compose logs -f
docker compose restart
docker compose down
```

---

## 🧾 Scripts Overview

Located in `/scripts` — fully portable.

### 🧹 Git Branch Cleanup Scripts
- `git-clean-branches.sh` (macOS/Linux)
- `git-clean-branches.ps1` (Windows)

Automatically remove:
- merged branches  
- stale branches  
- orphaned references  

### 🧪 Browser Automation / Puppeteer Setup
- macOS: `launch-browser-connector.sh`
- Windows: `launch-browser-connector.bat`

Launches browser with:
- remote debugging  
- isolated profile  
- Puppeteer-ready environment  

### 🤖 Ollama Model Bootstrap (Local AI)

Pull required local models into the persistent Ollama volume (`./docker/ollama`) so n8n can call them.

- macOS/Linux: `ollama-pull-models.sh`
- Windows: `ollama-pull-models.ps1`

Usage (macOS/Linux):
```bash
bash scripts/ollama-pull-models.sh
```

Usage (Windows PowerShell):
```powershell
powershell -ExecutionPolicy Bypass -File scripts\ollama-pull-models.ps1
```

Verify:
```bash
docker exec -it ollama ollama list
```

### 🔧 Other helpers coming soon:
- db sync scripts  
- translation pipelines  
- content processing tools  

---

## 📚 Learning Goals

This repo is a personal environment to master:

- Workflow automation with **n8n**
- Service orchestration using **Docker**
- Hybrid architecture (**PHP + Python**)
- OAuth-based automation (YouTube/TikTok)
- Translation & NLP pipelines
- CI/CD with GitHub Actions
- Building production-ready automation systems

---

## 👤 Author

**Son Vi**

- Facebook: https://facebook.com/son.vi.99  
- Email: `sonvi10101999@gmail.com`  
- Telegram: `0337 517 047`

If you’re into automation, DevOps, or business workflows — feel free to reach out.

---

## 📌 Branching Strategy

- **main** → stable + ongoing development  
- Feature branches will be merged then auto-cleaned using included Git scripts  

---

## 📄 License

This project is currently part of a personal R&D / automation lab.  
If you want to adapt it for production, please contact me.

---

_If this automation ecosystem inspires you, a ⭐ on GitHub would be awesome!_
