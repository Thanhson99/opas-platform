#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/.codex-tmp"
mkdir -p "$LOG_DIR"

FORCE_REBUILD=0

for arg in "$@"; do
  case "$arg" in
    --fresh|--build)
      FORCE_REBUILD=1
      ;;
    *)
      echo "Unknown argument: $arg"
      echo "Usage: ./scripts/start-local.sh [--fresh]"
      exit 1
      ;;
  esac
done

step_index=0
step_total=8

show_spinner() {
  local pid="$1"
  local message="$2"
  local frames='|/-\'
  local index=0

  while kill -0 "$pid" 2>/dev/null; do
    printf '\r[%d/%d] %s %c' "$step_index" "$step_total" "$message" "${frames:index++%${#frames}:1}"
    sleep 0.12
  done

  wait "$pid"
  local exit_code=$?
  if [[ $exit_code -ne 0 ]]; then
    printf '\r[%d/%d] %s failed\n' "$step_index" "$step_total" "$message"
    return "$exit_code"
  fi

  printf '\r[%d/%d] %s done\n' "$step_index" "$step_total" "$message"
}

run_step() {
  local message="$1"
  shift

  step_index=$((step_index + 1))
  local log_file="$LOG_DIR/step-${step_index}.log"

  (
    "$@"
  ) >"$log_file" 2>&1 &

  local pid=$!

  if ! show_spinner "$pid" "$message"; then
    echo "Step failed: $message"
    echo "Recent log output:"
    tail -n 40 "$log_file" || true
    exit 1
  fi
}

wait_for_url() {
  local url="$1"
  local max_attempts=60
  local attempt=1
  local http_code=""

  while (( attempt <= max_attempts )); do
    http_code="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 5 "$url" 2>/dev/null || true)"
    if [[ "$http_code" =~ ^2|^3 ]]; then
      return 0
    fi
    if [[ "$http_code" =~ ^5 ]]; then
      echo "Local web returned HTTP $http_code while waiting for readiness."
      echo "Check: docker compose logs laravel --tail=100"
      echo "Check: docker compose logs nginx --tail=100"
      return 1
    fi
    sleep 2
    attempt=$((attempt + 1))
  done

  echo "Timed out waiting for $url"
  return 1
}

run_step "Prepare env files" bash -lc "
  upsert_env_value() {
    local file=\"\$1\"
    local key=\"\$2\"
    local value=\"\$3\"
    if grep -qE \"^\${key}=\" \"\$file\"; then
      perl -0pi -e \"s/^\Q\${key}\E=.*/\${key}=\Q\${value}\E/m\" \"\$file\"
    else
      printf '%s=%s\n' \"\$key\" \"\$value\" >> \"\$file\"
    fi
  }

  [[ -f '$ROOT_DIR/.env' ]] || cp '$ROOT_DIR/.env.example' '$ROOT_DIR/.env'
  [[ -f '$ROOT_DIR/apps/laravel/.env' ]] || cp '$ROOT_DIR/apps/laravel/.env.example' '$ROOT_DIR/apps/laravel/.env'
  [[ -f '$ROOT_DIR/services/n8n/.env' ]] || cp '$ROOT_DIR/services/n8n/.env.example' '$ROOT_DIR/services/n8n/.env'
  [[ -f '$ROOT_DIR/services/python/.env' ]] || cp '$ROOT_DIR/services/python/.env.example' '$ROOT_DIR/services/python/.env'

  root_env='$ROOT_DIR/.env'
  laravel_env='$ROOT_DIR/apps/laravel/.env'

  db_name=\"\$(grep -E '^LARAVEL_DB_DATABASE=' \"\$root_env\" | tail -n 1 | cut -d= -f2-)\"
  db_user=\"\$(grep -E '^LARAVEL_DB_USERNAME=' \"\$root_env\" | tail -n 1 | cut -d= -f2-)\"
  db_password=\"\$(grep -E '^LARAVEL_DB_PASSWORD=' \"\$root_env\" | tail -n 1 | cut -d= -f2-)\"
  db_host=\"\$(grep -E '^LARAVEL_DB_HOST=' \"\$root_env\" | tail -n 1 | cut -d= -f2-)\"

  [[ -n \"\$db_name\" ]] && upsert_env_value \"\$laravel_env\" 'DB_DATABASE' \"\$db_name\"
  [[ -n \"\$db_user\" ]] && upsert_env_value \"\$laravel_env\" 'DB_USERNAME' \"\$db_user\"
  [[ -n \"\$db_password\" ]] && upsert_env_value \"\$laravel_env\" 'DB_PASSWORD' \"\$db_password\"
  [[ -n \"\$db_host\" ]] && upsert_env_value \"\$laravel_env\" 'DB_HOST' \"\$db_host\"
"

run_step "Create shared docker network" bash -lc "
  env_file='$ROOT_DIR/.env'
  network_name='shared_net'
  if [[ -f \"\$env_file\" ]]; then
    line=\"\$(grep -E '^SHARED_NET_NAME=' \"\$env_file\" | tail -n 1 || true)\"
    if [[ -n \"\$line\" ]]; then
      network_name=\"\${line#SHARED_NET_NAME=}\"
    fi
  fi
  docker network inspect \"\$network_name\" >/dev/null 2>&1 || docker network create \"\$network_name\" >/dev/null
"

if [[ $FORCE_REBUILD -eq 1 ]]; then
  run_step "Build and start containers" docker compose up -d --build
else
  run_step "Start containers" docker compose up -d
fi

if [[ $FORCE_REBUILD -eq 1 || ! -f "$ROOT_DIR/apps/laravel/vendor/autoload.php" ]]; then
  run_step "Install Laravel PHP dependencies" docker compose exec -T laravel composer install --no-interaction --prefer-dist --no-progress
else
  step_index=$((step_index + 1))
  printf '[%d/%d] Laravel PHP dependencies already available, skipping\n' "$step_index" "$step_total"
fi

if [[ $FORCE_REBUILD -eq 1 || ! -f "$ROOT_DIR/apps/laravel/public/build/manifest.json" ]]; then
  run_step "Build Laravel React frontend assets" docker run --rm -v "$ROOT_DIR/apps/laravel:/app" -w /app node:20-alpine sh -lc "npm ci --silent && npm run build -- --logLevel error"
else
  step_index=$((step_index + 1))
  printf '[%d/%d] Laravel frontend assets already built, skipping\n' "$step_index" "$step_total"
fi

if [[ $FORCE_REBUILD -eq 1 || ! -f "$ROOT_DIR/apps/laravel/.env" || -z "$(grep -E '^APP_KEY=' "$ROOT_DIR/apps/laravel/.env" | tail -n 1 | cut -d= -f2-)" ]]; then
  run_step "Generate Laravel app key" docker compose exec -T laravel php artisan key:generate --force --ansi
else
  step_index=$((step_index + 1))
  printf '[%d/%d] Laravel app key already set, skipping\n' "$step_index" "$step_total"
fi

run_step "Run Laravel migrations" docker compose exec -T laravel php artisan migrate --force --graceful --ansi
run_step "Seed Laravel database" docker compose exec -T laravel php artisan db:seed --force --ansi

run_step "Wait for local web" wait_for_url "http://localhost:8881"

open "http://localhost:8881" >/dev/null 2>&1 || true

cat <<'EOF'

Local stack is ready.

- Laravel App:      http://localhost:8881
- n8n:              http://localhost:5678
- LibreTranslate:   http://localhost:8884

EOF
