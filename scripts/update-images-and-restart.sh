#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="docker-compose.yml"
TARGET_SERVICES=("n8n" "ollama")   # Services to update

echo "============================================================"
echo "UPDATE DOCKER IMAGES AND RESTART CONTAINERS"
echo "Compose file : ${COMPOSE_FILE}"
echo "Services     : ${TARGET_SERVICES[*]}"
echo "============================================================"

echo ""
echo "[1/4] Pulling latest images..."
docker compose -f "$COMPOSE_FILE" pull "${TARGET_SERVICES[@]}"

echo ""
echo "[2/4] Recreating containers to apply new images..."
docker compose -f "$COMPOSE_FILE" up -d --force-recreate "${TARGET_SERVICES[@]}"

echo ""
echo "[3/4] Container status:"
docker compose -f "$COMPOSE_FILE" ps

echo ""
echo "[4/4] Checking versions:"
echo "- n8n version:"
docker exec -it n8n n8n --version || echo "  (n8n not ready)"
echo "- ollama version:"
docker exec -it ollama ollama --version || echo "  (ollama not ready)"

echo ""
echo "✅ Update completed successfully."
