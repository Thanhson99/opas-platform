#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/apps/laravel"
LOG_DIR="$APP_DIR/storage/logs"
SERVER_LOG="$LOG_DIR/opas-server.log"
VITE_LOG="$LOG_DIR/opas-vite.log"
QUEUE_LOG="$LOG_DIR/opas-queue.log"
PAIL_LOG="$LOG_DIR/opas-pail.log"
SERVER_PORT="${OPAS_SERVER_PORT:-8000}"
VITE_PORT="${OPAS_VITE_PORT:-5173}"
ENABLE_WORKERS="${OPAS_ENABLE_WORKERS:-0}"
RUN_MODE="${OPAS_RUN_MODE:-lite}"
LOCAL_ENV_ARGS=()

SERVER_PID=""
VITE_PID=""
QUEUE_PID=""
PAIL_PID=""

cleanup() {
  local exit_code=$?

  for pid in "$PAIL_PID" "$QUEUE_PID" "$VITE_PID" "$SERVER_PID"; do
    if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null || true
      wait "$pid" 2>/dev/null || true
    fi
  done

  exit "$exit_code"
}

wait_for_url() {
  local url="$1"
  local label="$2"
  local attempts="${3:-30}"

  for _ in $(seq 1 "$attempts"); do
    if curl -fsS "$url" >/dev/null 2>&1; then
      echo "[OPAS] $label is ready"
      return 0
    fi

    sleep 1
  done

  echo "[OPAS] $label did not become ready in time"
  return 1
}

wait_for_hot_file() {
  local attempts="${1:-30}"

  for _ in $(seq 1 "$attempts"); do
    if [ -f "$APP_DIR/public/hot" ]; then
      echo "[OPAS] Vite dev server is ready"
      return 0
    fi

    sleep 1
  done

  echo "[OPAS] Vite dev server did not become ready in time"
  return 1
}

env_value() {
  local key="$1"
  local file="$2"

  if [ ! -f "$file" ]; then
    return 1
  fi

  sed -n "s/^${key}=//p" "$file" | tail -n 1
}

prepare_local_runtime_env() {
  local env_file="$APP_DIR/.env"
  local db_host
  local db_connection

  db_host="$(env_value "DB_HOST" "$env_file" || true)"
  db_connection="$(env_value "DB_CONNECTION" "$env_file" || true)"

  if [ "${OPAS_FORCE_DOCKER_ENV:-0}" = "1" ]; then
    echo "[OPAS] Keeping Docker-oriented database settings from .env"
    return 0
  fi

  if [ "$db_connection" = "pgsql" ] && [ "$db_host" = "postgres" ]; then
    echo "[OPAS] Docker PostgreSQL host detected in .env"
    echo "[OPAS] Local run will use sqlite fallback unless OPAS_FORCE_DOCKER_ENV=1"

    mkdir -p "$APP_DIR/database"

    if [ ! -f "$APP_DIR/database/database.sqlite" ]; then
      touch "$APP_DIR/database/database.sqlite"
    fi

    LOCAL_ENV_ARGS=(
      "APP_URL=http://127.0.0.1:$SERVER_PORT"
      "DB_CONNECTION=sqlite"
      "DB_DATABASE=$APP_DIR/database/database.sqlite"
      "SESSION_DRIVER=file"
      "CACHE_STORE=file"
      "QUEUE_CONNECTION=sync"
    )

    echo "[OPAS] Running local migrations for sqlite fallback ..."
    env "${LOCAL_ENV_ARGS[@]}" php artisan migrate --graceful --ansi >/dev/null 2>&1 || true
    echo "[OPAS] Seeding local sqlite fallback ..."
    env "${LOCAL_ENV_ARGS[@]}" php artisan db:seed --ansi >/dev/null 2>&1 || true
  fi
}

trap cleanup INT TERM EXIT

cd "$APP_DIR"

echo "[OPAS] Preparing Laravel + React local workspace..."

if [ ! -f ".env" ]; then
  echo "[OPAS] Creating .env from .env.example"
  cp .env.example .env
fi

if [ ! -d "vendor" ]; then
  echo "[OPAS] Running composer install"
  composer install
fi

if [ ! -d "node_modules" ]; then
  echo "[OPAS] Running npm install"
  npm install
fi

if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
  echo "[OPAS] Generating APP_KEY"
  php artisan key:generate --ansi
fi

prepare_local_runtime_env

mkdir -p "$LOG_DIR"
rm -f "$APP_DIR/public/hot"
: > "$SERVER_LOG"
: > "$VITE_LOG"

echo "[OPAS] Starting Laravel server on http://127.0.0.1:$SERVER_PORT ..."
env "${LOCAL_ENV_ARGS[@]}" php artisan serve --host=127.0.0.1 --port="$SERVER_PORT" >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!
wait_for_url "http://127.0.0.1:$SERVER_PORT" "Laravel server"

echo "[OPAS] Starting Vite dev server on http://127.0.0.1:$VITE_PORT ..."
npm run dev -- --host 127.0.0.1 --port "$VITE_PORT" >"$VITE_LOG" 2>&1 &
VITE_PID=$!
wait_for_hot_file

if [ "$RUN_MODE" = "full" ]; then
  ENABLE_WORKERS=1
fi

if [ "$ENABLE_WORKERS" = "1" ]; then
  : > "$QUEUE_LOG"
  : > "$PAIL_LOG"

  echo "[OPAS] Starting queue worker in background ..."
  env "${LOCAL_ENV_ARGS[@]}" php artisan queue:listen --tries=1 >"$QUEUE_LOG" 2>&1 &
  QUEUE_PID=$!

  echo "[OPAS] Starting log tail in background ..."
  env "${LOCAL_ENV_ARGS[@]}" php artisan pail --timeout=0 >"$PAIL_LOG" 2>&1 &
  PAIL_PID=$!
else
  echo "[OPAS] Queue worker and pail are skipped by default"
  echo "[OPAS] Use OPAS_ENABLE_WORKERS=1 scripts/start-local-lite.sh if you need them"
fi

echo
echo "[OPAS] Local app is ready"
echo "[OPAS] Laravel: http://127.0.0.1:$SERVER_PORT"
echo "[OPAS] Vite:    http://127.0.0.1:$VITE_PORT"
echo "[OPAS] Logs:"
echo "[OPAS]   Laravel -> $SERVER_LOG"
echo "[OPAS]   Vite    -> $VITE_LOG"

if [ "$ENABLE_WORKERS" = "1" ]; then
  echo "[OPAS]   Queue   -> $QUEUE_LOG"
  echo "[OPAS]   Pail    -> $PAIL_LOG"
fi

echo "[OPAS] Use 'cd apps/laravel && composer check' for PHP checks"
echo "[OPAS] Use 'cd apps/laravel && npm run ci' for React lint/format/build verification"
echo "[OPAS] Press Ctrl+C to stop local services"

wait
