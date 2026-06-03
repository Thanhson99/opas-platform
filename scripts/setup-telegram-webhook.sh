#!/usr/bin/env bash

set -euo pipefail

# -----------------------------------------------------------------------------
# Path and execution context
# - APP_DIR/LARAVEL_ENV_FILE: Laravel runtime location and env source of truth.
# - LOG_DIR: per-step logs for inspect/register/delete/sync operations.
# -----------------------------------------------------------------------------
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/apps/laravel"
LARAVEL_ENV_FILE="$APP_DIR/.env"
LOG_DIR="$ROOT_DIR/.codex-tmp/telegram-webhook"

MODE="register"
PUBLIC_BASE_URL=""
DROP_PENDING_UPDATES=0
SYNC_COMMANDS=1
CLEAR_CONFIG=1
STEP_INDEX=0
STEP_TOTAL=4

mkdir -p "$LOG_DIR"

# Show supported modes and examples for webhook operations.
usage() {
  cat <<'EOF'
Usage:
  scripts/setup-telegram-webhook.sh <public-base-url> [--no-sync] [--no-config-clear] [--drop-pending-updates]
  scripts/setup-telegram-webhook.sh --inspect
  scripts/setup-telegram-webhook.sh --delete [--drop-pending-updates]
  scripts/setup-telegram-webhook.sh --sync-commands

Examples:
  scripts/setup-telegram-webhook.sh https://abc123.ngrok-free.app
  scripts/setup-telegram-webhook.sh https://abc123.ngrok-free.app --drop-pending-updates
  scripts/setup-telegram-webhook.sh --inspect
  scripts/setup-telegram-webhook.sh --delete
  scripts/setup-telegram-webhook.sh --sync-commands
EOF
}

# Interactive fallback when the script is run without arguments.
show_interactive_menu() {
  local selection=""
  local base_url=""
  local answer=""

  echo "Telegram webhook setup"
  echo "1. Register webhook"
  echo "2. Inspect webhook"
  echo "3. Delete webhook"
  echo "4. Sync bot commands"
  echo "5. Help"
  printf 'Choose an option [1-5]: '
  read -r selection

  case "$selection" in
    1)
      MODE="register"
      printf 'Public base URL (example: https://abc123.ngrok-free.app): '
      read -r base_url

      if [[ -z "$base_url" ]]; then
        echo "[OPAS] Public base URL is required." >&2
        exit 1
      fi

      PUBLIC_BASE_URL="$base_url"

      printf 'Drop pending Telegram updates? [Y/N]: '
      read -r answer

      if [[ "$answer" =~ ^[Yy]$ ]]; then
        DROP_PENDING_UPDATES=1
      fi
      ;;
    2)
      MODE="inspect"
      ;;
    3)
      MODE="delete"
      printf 'Drop pending Telegram updates while deleting? [y/N]: '
      read -r answer

      if [[ "$answer" =~ ^[Yy]$ ]]; then
        DROP_PENDING_UPDATES=1
      fi
      ;;
    4)
      MODE="sync-commands"
      ;;
    5)
      usage
      exit 0
      ;;
    *)
      echo "[OPAS] Invalid selection." >&2
      exit 1
      ;;
  esac
}

# Read a single key from .env without sourcing shell state.
read_env_value() {
  local file="$1"
  local key="$2"

  if [[ ! -f "$file" ]]; then
    return 1
  fi

  grep -E "^${key}=" "$file" | tail -n 1 | cut -d= -f2- | sed 's/^"//;s/"$//'
}

# Decide whether the current DB host suggests Docker-first artisan execution.
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

# Validate that a binary dependency exists before executing steps.
require_command() {
  local command_name="$1"

  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "[OPAS] Missing required command: $command_name" >&2
    exit 1
  fi
}

# Validate that an expected file is available.
require_file() {
  local path="$1"

  if [[ ! -f "$path" ]]; then
    echo "[OPAS] Required file not found: $path" >&2
    exit 1
  fi
}

# Trim trailing slash to avoid malformed webhook URL joins.
normalize_base_url() {
  local raw_url="$1"

  printf '%s' "${raw_url%/}"
}

# Build Telegram webhook endpoint expected by Laravel route definitions.
build_webhook_url() {
  local base_url="$1"

  printf '%s/api/telegram/auto-coding/webhook' "$base_url"
}

# Guard to prevent non-HTTPS webhook registration.
assert_https_url() {
  local url="$1"

  if [[ ! "$url" =~ ^https:// ]]; then
    echo "[OPAS] Telegram webhook URLs must use HTTPS: $url" >&2
    exit 1
  fi
}

# Run Laravel artisan command from the runtime that can reach the configured database.
run_artisan() {
  local db_host=""
  db_host="$(read_env_value "$LARAVEL_ENV_FILE" "DB_HOST" || true)"

  if should_use_docker "$db_host"; then
    (
      cd "$ROOT_DIR"
      docker compose exec -T laravel php artisan "$@"
    )
    return
  fi

  (
    cd "$APP_DIR"
    php artisan "$@"
  )
}

# Read the active Telegram bot runtime payload from Laravel.
read_telegram_runtime_json() {
  run_artisan opas:auto-coding:telegram:runtime
}

# Validate the database-backed default Telegram bot before mutating webhook state.
assert_required_telegram_runtime() {
  local runtime_json
  local missing_reason

  if ! runtime_json="$(read_telegram_runtime_json 2>&1)"; then
    echo "[OPAS] Could not read the database-backed Telegram bot runtime." >&2
    echo "$runtime_json" >&2
    exit 1
  fi

missing_reason="$(printf '%s' "$runtime_json" | php -r '
$payload = json_decode(stream_get_contents(STDIN), true);
if (! is_array($payload)) {
    echo "Unable to decode Telegram runtime payload.";
    exit(0);
}
$missing = [];
if (($payload["enabled"] ?? false) !== true) {
    $source = is_string($payload["source"] ?? null) ? $payload["source"] : "runtime";
    $missing[] = "bot runtime is disabled (source: ".$source.")";
}
if (! is_string($payload["bot_token"] ?? null) || trim((string) $payload["bot_token"]) === "") {
    $missing[] = "bot token is missing";
}
if (! is_string($payload["webhook_secret"] ?? null) || trim((string) $payload["webhook_secret"]) === "") {
    $missing[] = "webhook secret is missing";
}
$chatIds = $payload["allowed_chat_ids"] ?? [];
$userIds = $payload["allowed_user_ids"] ?? [];
if ((! is_array($chatIds) || count($chatIds) === 0) && (! is_array($userIds) || count($userIds) === 0)) {
    $missing[] = "at least one allowed chat id or user id is required";
}
if ($missing !== []) {
    echo "Telegram bot config is not ready: ".implode("; ", $missing).".";
    exit(0);
}
echo "";
')"

  if [[ -n "$missing_reason" ]]; then
    echo "[OPAS] $missing_reason" >&2
    echo "[OPAS] Open /admin/auto-coding/telegram-bots and finish configuring the default bot." >&2
    exit 1
  fi
}

# Render one spinner while a background step executes.
show_spinner() {
  local pid="$1"
  local message="$2"
  local frames='|/-\'
  local index=0

  while kill -0 "$pid" 2>/dev/null; do
    printf '\r[%d/%d] %s %c' "$STEP_INDEX" "$STEP_TOTAL" "$message" "${frames:index++%${#frames}:1}"
    sleep 0.12
  done

  wait "$pid"
  local exit_code=$?

  if [[ $exit_code -ne 0 ]]; then
    printf '\r[%d/%d] %s failed\n' "$STEP_INDEX" "$STEP_TOTAL" "$message"
    return "$exit_code"
  fi

  printf '\r[%d/%d] %s done\n' "$STEP_INDEX" "$STEP_TOTAL" "$message"
}

# Execute one step, capture logs, and emit concise operator-friendly output.
run_step() {
  local message="$1"
  local log_name="$2"
  shift 2

  STEP_INDEX=$((STEP_INDEX + 1))
  local log_file="$LOG_DIR/$log_name"

  (
    "$@"
  ) >"$log_file" 2>&1 &

  local pid=$!

  if ! show_spinner "$pid" "$message"; then
    echo "[OPAS] Step failed: $message" >&2
    echo "[OPAS] Recent log output:" >&2
    tail -n 40 "$log_file" >&2 || true
    exit 1
  fi
}

for arg in "$@"; do
  case "$arg" in
    --inspect)
      MODE="inspect"
      ;;
    --delete)
      MODE="delete"
      ;;
    --sync-commands)
      MODE="sync-commands"
      ;;
    --drop-pending-updates)
      DROP_PENDING_UPDATES=1
      ;;
    --no-sync)
      SYNC_COMMANDS=0
      ;;
    --no-config-clear)
      CLEAR_CONFIG=0
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    --*)
      echo "Unknown option: $arg" >&2
      usage
      exit 1
      ;;
    *)
      if [[ -n "$PUBLIC_BASE_URL" ]]; then
        echo "Only one public base URL can be provided." >&2
        usage
        exit 1
      fi

      PUBLIC_BASE_URL="$arg"
      ;;
  esac
done

if [[ $# -eq 0 ]]; then
  show_interactive_menu
fi

require_command php
require_file "$APP_DIR/artisan"
require_file "$LARAVEL_ENV_FILE"

if should_use_docker "$(read_env_value "$LARAVEL_ENV_FILE" "DB_HOST" || true)"; then
  require_command docker
fi

case "$MODE" in
  inspect)
    STEP_TOTAL=$(( CLEAR_CONFIG == 1 ? 2 : 1 ))

    if [[ $CLEAR_CONFIG -eq 1 ]]; then
      run_step "Clear Laravel config cache" "inspect-config-clear.log" run_artisan config:clear
    fi

    run_step "Inspect Telegram webhook state" "inspect-webhook.log" run_artisan opas:auto-coding:telegram:webhook
    ;;
  delete)
    assert_required_telegram_runtime
    STEP_TOTAL=$(( CLEAR_CONFIG == 1 ? 2 : 1 ))

    if [[ $CLEAR_CONFIG -eq 1 ]]; then
      run_step "Clear Laravel config cache" "delete-config-clear.log" run_artisan config:clear
    fi

    if [[ $DROP_PENDING_UPDATES -eq 1 ]]; then
      run_step "Delete Telegram webhook" "delete-webhook.log" run_artisan opas:auto-coding:telegram:webhook-delete --drop-pending-updates
    else
      run_step "Delete Telegram webhook" "delete-webhook.log" run_artisan opas:auto-coding:telegram:webhook-delete
    fi
    ;;
  sync-commands)
    assert_required_telegram_runtime
    STEP_TOTAL=$(( CLEAR_CONFIG == 1 ? 2 : 1 ))

    if [[ $CLEAR_CONFIG -eq 1 ]]; then
      run_step "Clear Laravel config cache" "sync-config-clear.log" run_artisan config:clear
    fi

    run_step "Sync Telegram bot commands" "sync-commands.log" run_artisan opas:auto-coding:telegram:commands-sync
    ;;
  register)
    if [[ -z "$PUBLIC_BASE_URL" ]]; then
      echo "[OPAS] A public base URL is required for webhook registration." >&2
      usage
      exit 1
    fi

    assert_required_telegram_runtime

    NORMALIZED_BASE_URL="$(normalize_base_url "$PUBLIC_BASE_URL")"
    WEBHOOK_URL="$(build_webhook_url "$NORMALIZED_BASE_URL")"
    assert_https_url "$WEBHOOK_URL"
    STEP_TOTAL=$(( 2 + CLEAR_CONFIG + SYNC_COMMANDS ))

    if [[ $CLEAR_CONFIG -eq 1 ]]; then
      run_step "Clear Laravel config cache" "register-config-clear.log" run_artisan config:clear
    fi

    if [[ $DROP_PENDING_UPDATES -eq 1 ]]; then
      run_step "Register Telegram webhook" "register-webhook.log" run_artisan opas:auto-coding:telegram:webhook "$WEBHOOK_URL" --drop-pending-updates
    else
      run_step "Register Telegram webhook" "register-webhook.log" run_artisan opas:auto-coding:telegram:webhook "$WEBHOOK_URL"
    fi

    if [[ $SYNC_COMMANDS -eq 1 ]]; then
      run_step "Sync Telegram bot commands" "register-commands.log" run_artisan opas:auto-coding:telegram:commands-sync
    fi

    run_step "Verify Telegram webhook state" "register-verify.log" run_artisan opas:auto-coding:telegram:webhook
    ;;
esac

echo
echo "[OPAS] Telegram webhook setup completed."
