#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/apps/laravel"
MODE="auto"
CHECK_FRONTEND=0

for arg in "$@"; do
  case "$arg" in
    --docker)
      MODE="docker"
      ;;
    --local)
      MODE="local"
      ;;
    --frontend|--with-frontend)
      CHECK_FRONTEND=1
      ;;
    --ci)
      CHECK_FRONTEND=1
      ;;
    *)
      echo "Unknown argument: $arg"
      echo "Usage: ./scripts/test-laravel.sh [--docker|--local] [--with-frontend|--ci]"
      exit 1
      ;;
  esac
done

run_in_docker() {
  docker compose exec -T laravel composer check

  if [[ $CHECK_FRONTEND -eq 1 ]]; then
    run_frontend_ci
  fi
}

run_local() {
  cd "$APP_DIR"
  composer check

  if [[ $CHECK_FRONTEND -eq 1 ]]; then
    npm run ci
  fi
}

run_frontend_ci() {
  docker run --rm -v "$APP_DIR:/app" -w /app node:20-alpine sh -lc '
    if [ ! -d node_modules ] || [ ! -f node_modules/.package-lock.json ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then
      npm ci --silent
    fi
    npm run ci
  '
}

if [[ "$MODE" == "docker" ]]; then
  run_in_docker
  exit 0
fi

if [[ "$MODE" == "local" ]]; then
  run_local
  exit 0
fi

if docker compose ps --status running --services 2>/dev/null | grep -qx "laravel"; then
  run_in_docker
else
  run_local
fi
