#!/usr/bin/env bash
set -euo pipefail

# Make sure we're in repo root (script can run from anywhere)
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Config
OLLAMA_CONTAINER="${OLLAMA_CONTAINER:-ollama}"
MODELS_DEFAULT=("qwen2.5:7b" "mistral:7b")
MODELS=("${@:-${MODELS_DEFAULT[@]}}")

command -v docker >/dev/null 2>&1 || { echo "❌ docker not found"; exit 1; }

echo "==> Starting ollama container (if not running)..."
docker compose up -d ollama

echo "==> Waiting for Ollama API..."
READY=0
for i in {1..60}; do
  if docker exec "$OLLAMA_CONTAINER" sh -lc "ollama list >/dev/null 2>&1"; then
    READY=1
    break
  fi
  sleep 1
done

if [[ "$READY" -ne 1 ]]; then
  echo "❌ Ollama did not become ready in time."
  echo "Try: docker compose logs -f ollama"
  exit 1
fi

echo "==> Pulling models into ./docker/ollama (persistent volume)..."
for m in "${MODELS[@]}"; do
  echo "---- Pull: $m"
  docker exec -it "$OLLAMA_CONTAINER" ollama pull "$m" || {
    echo "❌ Failed to pull model: $m"
    echo "Available local models:"
    docker exec -it "$OLLAMA_CONTAINER" ollama list || true
    exit 1
  }
done

echo "==> Installed models:"
docker exec -it "$OLLAMA_CONTAINER" ollama list

echo "✅ Done. n8n can call: http://ollama:11434/api/chat"
