#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/apps/laravel"
LARAVEL_ENV_FILE="$APP_DIR/.env"
MODE="auto"

if [[ ! -d "$APP_DIR" ]]; then
  echo "[OPAS] Laravel app directory not found: $APP_DIR" >&2
  exit 1
fi

for arg in "$@"; do
  case "$arg" in
    --docker)
      MODE="docker"
      ;;
    --local)
      MODE="local"
      ;;
    *)
      echo "Unknown argument: $arg" >&2
      echo "Usage: scripts/run-laravel-queue.sh [--docker|--local]" >&2
      exit 1
      ;;
  esac
done

read_env_value() {
  local file="$1"
  local key="$2"

  if [[ ! -f "$file" ]]; then
    return 1
  fi

  grep -E "^${key}=" "$file" | tail -n 1 | cut -d= -f2- | sed 's/^"//;s/"$//'
}

should_use_docker() {
  local db_host="${1:-}"

  case "$db_host" in
    postgres|pgsql|mysql|mariadb|laravel|laravel-app)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

docker_laravel_running() {
  docker compose ps --status running laravel >/dev/null 2>&1
}

run_local_worker() {
  cd "$APP_DIR"

  if [[ ! -f artisan ]]; then
    echo "[OPAS] artisan not found in $APP_DIR" >&2
    exit 1
  fi

  echo "[OPAS] Running Laravel queue worker locally from $APP_DIR"
  echo "[OPAS] Command: php artisan queue:work --queue=default --tries=3 --timeout=90"

  php artisan queue:work --queue=default --tries=3 --timeout=90
}

run_docker_worker() {
  cd "$ROOT_DIR"

  if ! docker_laravel_running; then
    echo "[OPAS] Docker Laravel service is not running." >&2
    echo "[OPAS] Start it first with: scripts/start-local.sh" >&2
    exit 1
  fi

  echo "[OPAS] Running Laravel queue worker inside Docker container"
  echo "[OPAS] Command: docker compose exec laravel php artisan queue:work --queue=default --tries=3 --timeout=90"

  docker compose exec laravel php artisan queue:work --queue=default --tries=3 --timeout=90
}

DB_HOST_VALUE="$(read_env_value "$LARAVEL_ENV_FILE" "DB_HOST" || true)"

case "$MODE" in
  docker)
    run_docker_worker
    ;;
  local)
    run_local_worker
    ;;
  auto)
    if should_use_docker "$DB_HOST_VALUE"; then
      run_docker_worker
    else
      run_local_worker
    fi
    ;;
esac
