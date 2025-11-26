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
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Database-4169E1?logo=postgresql&logoColor=white)
![CI/CD](https://img.shields.io/badge/GitHub%20Actions-CI%2FCD-2088FF?logo=githubactions)
![License](https://img.shields.io/badge/License-Custom-blue)

# Laravel + N8N Automation Lab

> 🔧 A DevOps & Automation playground combining **Laravel**, **n8n**, **Python microservices**, **PostgreSQL**, and **Docker** – for real-world workflows such as multi-platform video uploads, translation systems, trading bots, dropshipping automation, and more.

---

## ✨ What is this repository?

This repository is my personal **automation lab** — a place to build, test, and grow real-world workflows using:

- **Laravel** → Main web interface & user dashboard  
- **n8n** → Workflow automation engine (running hidden in background)  
- **Python services** → Helper APIs for automation & data processing  
- **PostgreSQL** → Shared database  
- **LibreTranslate** → Local translation service (Vietnamese support included)  
- **Docker** → The entire environment is containerized  
- **GitHub Actions** → CI/CD pipelines  
- **Cross-platform scripts** → Tools for macOS & Windows

It serves as a base for automating:

- 📹 Multi-platform video publishing (YouTube, TikTok, …)  
- 🛒 Dropshipping & affiliate marketing  
- 📈 Trading bot infrastructure  
- ✉️ Notification / alert pipelines  
- 🗂 Data processing & migration  
- 🧹 DevOps tooling & code quality automation  

This is **not** a simple demo — it’s a growing automation ecosystem.

---

## 🧠 High-Level Architecture

```text
┌──────────────────────────┐
│        Users / UI        │
│      (Laravel App)       │
└────────────┬─────────────┘
             │ HTTP / API
┌────────────▼─────────────┐       ┌─────────────────────────┐
│        NGINX Proxy       │──────▶│   n8n (Workflows)       │
└────────────┬─────────────┘       └─────────────────────────┘
             │
             │                  ┌─────────────────────────────┐
             │────────────────▶ │ Python microservices        │
             │                  │ Helper APIs for automation  │
             │                  └─────────────────────────────┘
             │
             │                  ┌─────────────────────────────┐
             └────────────────▶ │ LibreTranslate (local AI)   │
                                │ VI/EN translation workflows │
                                └─────────────────────────────┘

               ┌────────────────────────────────────────────┐
               │               PostgreSQL DB                │
               │ Shared by Laravel + n8n + Python services  │
               └────────────────────────────────────────────┘
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
- 📹 YouTube Upload (YouTube Data API v3 + OAuth)
- 🎵 TikTok Upload / Automation (planned)
- 💾 Database Pipelines
- 🌍 Translation workflows via LibreTranslate
- 🔑 OAuth integrations

### **Tooling**
- 🧹 **Scripts for macOS + Windows (Git, Puppeteer, Browser Automation)**
- 📦 Local translation model support (Vietnamese)
- 🧪 GitHub Actions CI/CD

---

## 📂 Project Structure

```text
LARAVEL-N8N-AUTOMATION/
├── .github/                 # CI/CD pipelines
├── docker/                  # Docker-related configs
├── laravel/                 # Laravel application
├── libretranslate/          # LibreTranslate service
├── libretranslate_models/   # Local translation models (e.g. VI)
├── n8n/                     # n8n data, credentials, workflows
├── nginx/                   # nginx configuration
├── python-services/         # Python helper microservices
├── scripts/                 # Automation scripts (macOS + Windows)
├── videos/                  # Raw videos to process
├── videos_uploaded/         # Videos after upload automation
├── .gitignore
├── .pr-agent.yaml           # AI code review config (optional)
├── docker-compose.yml
└── README.md
```

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
- Vietnamese optimized workflows
- Laravel + n8n endpoint wrappers
- Python microservices for advanced text processing  
  (e.g., cleanup, metadata extraction)

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
- TikTok auto-upload workflow  
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
- Advanced analytics dashboard

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
