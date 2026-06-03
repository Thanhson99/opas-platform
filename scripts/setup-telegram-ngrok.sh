#!/usr/bin/env bash

set -euo pipefail

# -----------------------------------------------------------------------------
# Path and runtime configuration
# - ROOT_DIR/APP_DIR: repository and Laravel app roots.
# - LOG_DIR/PID files: state files for ngrok and worker lifecycle management.
# -----------------------------------------------------------------------------
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT_DIR="$ROOT_DIR/scripts"
APP_DIR="$ROOT_DIR/apps/laravel"
LARAVEL_ENV_FILE="$APP_DIR/.env"
LOG_DIR="$ROOT_DIR/.codex-tmp/telegram-ngrok"
PID_FILE="$LOG_DIR/ngrok.pid"
LOG_FILE="$LOG_DIR/ngrok.log"
WORKER_PID_FILE="$LOG_DIR/auto-coding-worker.pid"
WORKER_LOG_FILE="$LOG_DIR/auto-coding-worker.log"
API_URL="http://127.0.0.1:4040/api/tunnels"
TUNNEL_URL_FILE="$LOG_DIR/public-url.txt"

LARAVEL_PORT="${LARAVEL_PORT:-8881}"
NGROK_BIN="${NGROK_BIN:-ngrok}"
NGROK_REGION="${NGROK_REGION:-}"
NGROK_HOST_HEADER="${NGROK_HOST_HEADER:-rewrite}"
TELEGRAM_LOCALE="${TELEGRAM_LOCALE:-en}"
LOCALE_WAS_SET=0
DROP_PENDING_UPDATES=0
MODE="start"
STEP_INDEX=0
STEP_TOTAL=9

mkdir -p "$LOG_DIR"

# Show command help and examples for operator usage.
usage() {
  cat <<'EOF'
Usage:
  scripts/setup-telegram-ngrok.sh start [--port=8881] [--lang=en|vi] [--drop-pending-updates]
  scripts/setup-telegram-ngrok.sh status
  scripts/setup-telegram-ngrok.sh stop

Behavior:
  - starts ngrok for the Laravel app port
  - starts the auto-coding worker
  - waits for a public HTTPS tunnel
  - registers the Telegram webhook automatically
  - syncs Telegram bot commands automatically through setup-telegram-webhook.sh

Examples:
  scripts/setup-telegram-ngrok.sh start
  scripts/setup-telegram-ngrok.sh start --port=8881 --lang=vi
  scripts/setup-telegram-ngrok.sh start --drop-pending-updates
  scripts/setup-telegram-ngrok.sh status
  scripts/setup-telegram-ngrok.sh stop
EOF
}

read_prompt() {
  local prompt="$1"
  local default_value="${2:-}"
  local answer=""

  printf '%s' "$prompt" >&2

  if ! read -r answer; then
    printf '%s' "$default_value"
    return 0
  fi

  if [[ -z "$answer" ]]; then
    printf '%s' "$default_value"
    return 0
  fi

  printf '%s' "$answer"
}

# Interactive menu for users who run the script without CLI flags.
show_interactive_menu() {
  local selection=""
  local custom_port=""
  local drop_pending_answer=""
  local language_answer=""

  echo "Telegram ngrok setup"
  echo "1. Start"
  echo "2. Status"
  echo "3. Stop"
  echo "4. Help"
  selection="$(read_prompt 'Choose [1-4] (1): ' '1')"

  case "$selection" in
    1)
      language_answer="$(read_prompt "Language [EN/VI] (${TELEGRAM_LOCALE}): " "$TELEGRAM_LOCALE")"

      if [[ -n "$language_answer" ]]; then
        TELEGRAM_LOCALE="$language_answer"
      fi

      custom_port="$(read_prompt "Laravel port [${LARAVEL_PORT}]: " "$LARAVEL_PORT")"

      if [[ -n "$custom_port" ]]; then
        LARAVEL_PORT="$custom_port"
      fi

      drop_pending_answer="$(read_prompt 'Drop pending Telegram updates? [Y/N] (N): ' 'N')"

      if [[ "$drop_pending_answer" =~ ^[Yy]$ ]]; then
        DROP_PENDING_UPDATES=1
      fi
      ;;
    2)
      MODE="status"
      ;;
    3)
      MODE="stop"
      ;;
    4)
      usage
      exit 0
      ;;
    *)
      echo "[OPAS] Invalid selection." >&2
      exit 1
      ;;
  esac
}

# Ensure a required binary exists before continuing.
require_command() {
  local command_name="$1"

  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "[OPAS] Missing required command: $command_name" >&2
    exit 1
  fi
}

# Read one key from .env without loading shell exports.
read_env_value() {
  local file="$1"
  local key="$2"

  if [[ ! -f "$file" ]]; then
    return 1
  fi

  grep -E "^${key}=" "$file" | tail -n 1 | cut -d= -f2- | sed 's/^"//;s/"$//'
}

# Normalize locale input to the only supported values: en | vi.
normalize_locale() {
  local value="${1:-en}"
  value="$(printf '%s' "$value" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')"

  case "$value" in
    vi)
      printf 'vi'
      ;;
    *)
      printf 'en'
      ;;
  esac
}

# Update or append one key=value pair in .env.
upsert_env_value() {
  local file="$1"
  local key="$2"
  local value="$3"

  if grep -qE "^${key}=" "$file"; then
    perl -0pi -e "s/^\\Q${key}\\E=.*/${key}=\\Q${value}\\E/m" "$file"
  else
    printf '%s=%s\n' "$key" "$value" >> "$file"
  fi
}

# Decide whether the current DB host suggests Docker-first execution.
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

# Run Laravel artisan in the same runtime that can reach the configured database.
run_laravel_artisan() {
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

# Persist Telegram locale on the default database-backed bot so webhook/worker responses stay consistent.
persist_telegram_locale() {
  TELEGRAM_LOCALE="$(normalize_locale "$TELEGRAM_LOCALE")"

  run_laravel_artisan opas:auto-coding:telegram:default-bot:update --locale="$TELEGRAM_LOCALE" >/dev/null
}

# Import .env Telegram secrets and allow lists into the database-backed default bot.
bootstrap_telegram_bot_from_env() {
  run_laravel_artisan opas:auto-coding:telegram:default-bot:import-env >/dev/null || true
}

validate_telegram_bot_runtime() {
  local runtime_json=""
  local missing_reason=""

  if ! runtime_json="$(run_laravel_artisan opas:auto-coding:telegram:runtime 2>&1)"; then
    echo "[OPAS] Could not read Telegram bot runtime config." >&2
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
    echo "[OPAS] Configure it in one of these ways:" >&2
    echo "[OPAS] 1. Admin UI: /admin/auto-coding/telegram-bots" >&2
    echo "[OPAS] 2. .env bootstrap keys: AUTO_CODING_TELEGRAM_BOT_TOKEN, AUTO_CODING_TELEGRAM_WEBHOOK_SECRET, AUTO_CODING_TELEGRAM_ALLOWED_CHAT_IDS or AUTO_CODING_TELEGRAM_ALLOWED_USER_IDS" >&2
    exit 1
  fi
}

# Convert locale to UI uppercase label for summary output.
telegram_locale_upper() {
  printf '%s' "$TELEGRAM_LOCALE" | tr '[:lower:]' '[:upper:]'
}

# Print a post-setup checklist for first-time operators.
print_start_summary() {
  local locale_label
  locale_label="$(telegram_locale_upper)"

  echo

  if [[ "$TELEGRAM_LOCALE" == "vi" ]]; then
    echo "[OPAS] Telegram tunnel, auto-coding worker, và webhook đã sẵn sàng."
    echo "[OPAS] Ngôn ngữ Telegram hiện tại: ${locale_label}"
    echo "[OPAS] Trong Telegram: gửi /start để bật chat Codex, /stop để tắt."
    echo "[OPAS] Lệnh phụ: /queue, /changes latest, /clear, /clear_all."
    echo "[OPAS] Giữ ngrok chạy trong lúc test Telegram."
    echo "[OPAS] Dừng lại sau này bằng: scripts/setup-telegram-ngrok.sh stop"
    return
  fi

  echo "[OPAS] Telegram tunnel, auto-coding worker, and webhook are ready."
  echo "[OPAS] Current Telegram language: ${locale_label}"
  echo "[OPAS] In Telegram: send /start to enable Codex chat, /stop to disable it."
  echo "[OPAS] Helper commands: /queue, /changes latest, /clear, /clear_all."
  echo "[OPAS] Keep ngrok running while testing Telegram."
  echo "[OPAS] Stop it later with: scripts/setup-telegram-ngrok.sh stop"
}

# Verify the laravel container is currently running.
docker_laravel_running() {
  local container_state=""
  container_state="$(cd "$ROOT_DIR" && docker compose ps --format '{{.State}}' laravel 2>/dev/null | head -n 1 || true)"

  if [[ -z "$container_state" ]]; then
    return 1
  fi

  [[ "$container_state" == "running" ]]
}

auto_coding_container_repository_path() {
  local container_repository_path=""
  container_repository_path="$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_CONTAINER_REPOSITORY_PATH" || true)"
  printf '%s' "${container_repository_path:-/workspace/repo}"
}

# Verify the laravel container can see a git repository.
docker_repository_ready() {
  local container_repository_path=""
  container_repository_path="$(auto_coding_container_repository_path)"

  docker compose exec -T laravel sh -lc 'git -C "$1" rev-parse --show-toplevel >/dev/null 2>&1' sh "$container_repository_path"
}

auto_coding_provider() {
  local provider=""
  provider="$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_PROVIDER" || true)"
  printf '%s' "$(printf '%s' "$provider" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')"
}

resolve_codex_executable() {
  local configured_executable="$1"
  local discovered_executable=""

  if [[ -x "$configured_executable" ]]; then
    printf '%s' "$configured_executable"
    return 0
  fi

  if command -v "$configured_executable" >/dev/null 2>&1; then
    command -v "$configured_executable"
    return 0
  fi

  if [[ "$configured_executable" == "codex" && -d "$HOME/.vscode/extensions" ]]; then
    discovered_executable="$(find "$HOME/.vscode/extensions" -path '*/bin/*/codex' -type f 2>/dev/null | sort | tail -n 1)"

    if [[ -n "$discovered_executable" && -x "$discovered_executable" ]]; then
      printf '%s' "$discovered_executable"
      return 0
    fi
  fi

  return 1
}

# Confirm the local Laravel HTTP target is reachable before opening ngrok.
local_laravel_target_ready() {
  local http_code=""

  http_code="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 5 "http://127.0.0.1:${LARAVEL_PORT}/" 2>/dev/null || true)"

  [[ "$http_code" =~ ^2|^3 ]]
}

# Render one spinner while a background step is still running.
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

# Run a step in background, show spinner, and print tail logs on failure.
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

install_ngrok_if_missing() {
  if command -v "$NGROK_BIN" >/dev/null 2>&1; then
    return 0
  fi

  if command -v brew >/dev/null 2>&1; then
    brew install ngrok/ngrok/ngrok
  elif command -v apt-get >/dev/null 2>&1; then
    sudo apt-get update
    sudo apt-get install -y ngrok
  elif command -v snap >/dev/null 2>&1; then
    sudo snap install ngrok
  else
    echo "[OPAS] Could not install ngrok automatically." >&2
    echo "[OPAS] Install ngrok manually, then rerun this script." >&2
    exit 1
  fi

  if ! command -v "$NGROK_BIN" >/dev/null 2>&1; then
    echo "[OPAS] ngrok installation did not expose the command in PATH." >&2
    exit 1
  fi
}

read_pid() {
  local file_path="${1:-$PID_FILE}"

  if [[ -f "$file_path" ]]; then
    cat "$file_path"
    return 0
  fi

  return 1
}

process_running() {
  local pid="$1"

  if [[ -z "$pid" ]]; then
    return 1
  fi

  kill -0 "$pid" 2>/dev/null
}

ngrok_running() {
  local pid="$1"

  process_running "$pid"
}

find_ngrok_pids() {
  if ! command -v pgrep >/dev/null 2>&1; then
    return 0
  fi

  pgrep -f "ngrok.*http.*${LARAVEL_PORT}" 2>/dev/null || true
}

resolve_ngrok_pid() {
  local recorded_pid
  recorded_pid="$(read_pid || true)"

  if [[ -n "$recorded_pid" ]] && ngrok_running "$recorded_pid"; then
    printf '%s\n' "$recorded_pid"
    return 0
  fi

  local discovered_pid=""
  discovered_pid="$(find_ngrok_pids | head -n 1)"

  if [[ -n "$discovered_pid" ]]; then
    echo "$discovered_pid" >"$PID_FILE"
    printf '%s\n' "$discovered_pid"
    return 0
  fi

  rm -f "$PID_FILE"

  return 1
}

stop_existing_ngrok() {
  local pid_list=()
  local recorded_pid
  recorded_pid="$(read_pid || true)"

  if [[ -n "$recorded_pid" ]]; then
    pid_list+=("$recorded_pid")
  fi

  while IFS= read -r discovered_pid; do
    if [[ -n "$discovered_pid" ]]; then
      pid_list+=("$discovered_pid")
    fi
  done < <(find_ngrok_pids)

  if [[ ${#pid_list[@]} -eq 0 ]]; then
    rm -f "$PID_FILE"
    return 0
  fi

  while IFS= read -r pid; do
    if ngrok_running "$pid"; then
      kill "$pid" 2>/dev/null || true

      for _ in $(seq 1 20); do
        if ! ngrok_running "$pid"; then
          break
        fi

        sleep 0.2
      done
    fi
  done < <(printf '%s\n' "${pid_list[@]}" | awk 'NF {print $0}' | sort -u)

  while IFS= read -r discovered_pid; do
    if [[ -n "$discovered_pid" ]] && ngrok_running "$discovered_pid"; then
      kill -9 "$discovered_pid" 2>/dev/null || true
      sleep 0.1
    fi
  done < <(find_ngrok_pids)

  rm -f "$PID_FILE"
}

ngrok_tunnel_available() {
  local https_url=""
  https_url="$(extract_https_url)"

  [[ -n "$https_url" ]]
}

stop_existing_worker() {
  local pid
  pid="$(read_pid "$WORKER_PID_FILE" || true)"

  if [[ -z "$pid" ]]; then
    rm -f "$WORKER_PID_FILE"
    return 0
  fi

  if process_running "$pid"; then
    kill "$pid" 2>/dev/null || true

    for _ in $(seq 1 20); do
      if ! process_running "$pid"; then
        break
      fi

      sleep 0.2
    done
  fi

  rm -f "$WORKER_PID_FILE"
}

start_ngrok() {
  local command=("$NGROK_BIN" "http" "$LARAVEL_PORT" "--log=stdout")

  if [[ -n "$NGROK_REGION" ]]; then
    command+=("--region=$NGROK_REGION")
  fi

  if [[ -n "$NGROK_HOST_HEADER" ]]; then
    command+=("--host-header=$NGROK_HOST_HEADER")
  fi

  (
    cd "$ROOT_DIR"
    "${command[@]}"
  ) >"$LOG_FILE" 2>&1 &

  local pid=$!
  echo "$pid" >"$PID_FILE"
}

wait_for_tunnel_to_file() {
  local pid="$1"
  wait_for_https_tunnel "$pid" >"$TUNNEL_URL_FILE"
}

register_webhook_from_tunnel() {
  local public_base_url
  public_base_url="$(cat "$TUNNEL_URL_FILE")"

  if [[ $DROP_PENDING_UPDATES -eq 1 ]]; then
    "$SCRIPT_DIR/setup-telegram-webhook.sh" "$public_base_url" --drop-pending-updates
  else
    "$SCRIPT_DIR/setup-telegram-webhook.sh" "$public_base_url"
  fi
}

extract_https_url() {
  curl -fsS "$API_URL" 2>/dev/null | php -r '
    $json = json_decode(stream_get_contents(STDIN), true);
    if (! is_array($json) || ! isset($json["tunnels"]) || ! is_array($json["tunnels"])) {
        exit(1);
    }
    foreach ($json["tunnels"] as $tunnel) {
        if (! is_array($tunnel)) {
            continue;
        }
        $publicUrl = $tunnel["public_url"] ?? null;
        if (is_string($publicUrl) && str_starts_with($publicUrl, "https://")) {
            echo $publicUrl;
            exit(0);
        }
    }
    exit(1);
  ' || true
}

wait_for_https_tunnel() {
  local pid="$1"
  local max_attempts=40
  local attempt=1
  local https_url=""

  while (( attempt <= max_attempts )); do
    if ! ngrok_running "$pid"; then
      echo "[OPAS] ngrok exited before a tunnel became available." >&2
      echo "[OPAS] Recent ngrok log:" >&2
      tail -n 40 "$LOG_FILE" >&2 || true
      exit 1
    fi

    https_url="$(extract_https_url)"

    if [[ -n "$https_url" ]]; then
      printf '%s' "$https_url"
      return 0
    fi

    sleep 0.5
    attempt=$((attempt + 1))
  done

  echo "[OPAS] Timed out waiting for ngrok HTTPS tunnel." >&2
  echo "[OPAS] Recent ngrok log:" >&2
  tail -n 40 "$LOG_FILE" >&2 || true
  exit 1
}

start_auto_coding_worker() {
  local db_host=""
  local provider=""
  db_host="$(read_env_value "$LARAVEL_ENV_FILE" "DB_HOST" || true)"
  provider="$(auto_coding_provider)"

  if [[ "$provider" == "codex" ]]; then
    start_local_codex_worker "$db_host"
    return
  fi

  if ! should_use_docker "$db_host"; then
    echo "[OPAS] Docker worker auto-start is only supported when DB_HOST points to the Docker stack." >&2
    exit 1
  fi

  if ! command -v docker >/dev/null 2>&1; then
    echo "[OPAS] Docker is required to run the Laravel auto-coding worker." >&2
    exit 1
  fi

  if ! docker_laravel_running; then
    echo "[OPAS] Docker Laravel service is not running." >&2
    echo "[OPAS] Start it first with: scripts/start-local.sh" >&2
    exit 1
  fi

  if ! docker_repository_ready; then
    echo "[OPAS] Docker worker cannot access the repository git root." >&2
    echo "[OPAS] Recreate the laravel container so the $(auto_coding_container_repository_path) mount is applied." >&2
    echo "[OPAS] Run: docker compose up -d --force-recreate laravel" >&2
    exit 1
  fi

  (
    cd "$ROOT_DIR"
    docker compose exec -T laravel php artisan opas:auto-coding:work --execute --interval=5 --max-iterations=0
  ) >"$WORKER_LOG_FILE" 2>&1 &

  local pid=$!
  echo "$pid" >"$WORKER_PID_FILE"

  sleep 1

  if ! process_running "$pid"; then
    echo "[OPAS] Docker auto-coding worker exited immediately." >&2
    tail -n 40 "$WORKER_LOG_FILE" >&2 || true
    exit 1
  fi
}

start_local_codex_worker() {
  local db_host="$1"
  local host_db_host=""
  local db_port=""
  local db_database=""
  local db_username=""
  local db_password=""
  local repository_path=""
  local prompt_path=""
  local codex_executable=""

  codex_executable="$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_CODEX_EXECUTABLE" || true)"
  codex_executable="${codex_executable:-codex}"

  if ! codex_executable="$(resolve_codex_executable "$codex_executable")"; then
    echo "[OPAS] AUTO_CODING_PROVIDER=codex but Codex CLI is not available on the host PATH or configured executable path." >&2
    echo "[OPAS] Open VS Code/Codex once or set AUTO_CODING_CODEX_EXECUTABLE to the Codex CLI path." >&2
    exit 1
  fi

  if should_use_docker "$db_host" && ! docker_laravel_running; then
    echo "[OPAS] Docker Laravel service is not running." >&2
    echo "[OPAS] Start it first with: scripts/start-local.sh" >&2
    exit 1
  fi

  host_db_host="$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_HOST_DB_HOST" || true)"
  host_db_host="${host_db_host:-127.0.0.1}"
  db_port="$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_HOST_DB_PORT" || true)"
  db_port="${db_port:-$(read_env_value "$LARAVEL_ENV_FILE" "DB_PORT" || true)}"
  db_database="$(read_env_value "$LARAVEL_ENV_FILE" "DB_DATABASE" || true)"
  db_username="$(read_env_value "$LARAVEL_ENV_FILE" "DB_USERNAME" || true)"
  db_password="$(read_env_value "$LARAVEL_ENV_FILE" "DB_PASSWORD" || true)"
  repository_path="$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_LOCAL_WORKER_REPOSITORY_PATH" || true)"
  repository_path="${repository_path:-$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_DEFAULT_REPOSITORY_PATH" || true)}"
  repository_path="${repository_path:-$ROOT_DIR}"
  prompt_path="$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_LOCAL_WORKER_PROMPT_PATH" || true)"
  prompt_path="${prompt_path:-$(read_env_value "$LARAVEL_ENV_FILE" "AUTO_CODING_PROMPT_PATH" || true)}"
  prompt_path="${prompt_path:-$repository_path/ai-local/agents/laravel-n8n-orchestrator.md}"

  (
    cd "$APP_DIR"
    env \
      DB_HOST="$host_db_host" \
      DB_PORT="${db_port:-5432}" \
      DB_DATABASE="${db_database:-laravel}" \
      DB_USERNAME="${db_username:-root}" \
      DB_PASSWORD="${db_password:-}" \
      AUTO_CODING_DEFAULT_REPOSITORY_PATH="$repository_path" \
      AUTO_CODING_PROMPT_PATH="$prompt_path" \
      AUTO_CODING_CODEX_EXECUTABLE="$codex_executable" \
      php artisan opas:auto-coding:work --execute --interval=5 --max-iterations=0
  ) >"$WORKER_LOG_FILE" 2>&1 &

  local pid=$!
  echo "$pid" >"$WORKER_PID_FILE"

  sleep 1

  if ! process_running "$pid"; then
    echo "[OPAS] Local Codex auto-coding worker exited immediately." >&2
    tail -n 40 "$WORKER_LOG_FILE" >&2 || true
    exit 1
  fi
}

ensure_local_runtime_ready() {
  local db_host=""
  db_host="$(read_env_value "$LARAVEL_ENV_FILE" "DB_HOST" || true)"

  if should_use_docker "$db_host"; then
    if ! command -v docker >/dev/null 2>&1; then
      echo "[OPAS] Docker is required to run the Laravel auto-coding worker." >&2
      exit 1
    fi

    if ! docker_laravel_running; then
      echo "[OPAS] Docker Laravel service is not running." >&2
      echo "[OPAS] Start it first with: scripts/start-local.sh" >&2
      exit 1
    fi

    if ! docker_repository_ready; then
      echo "[OPAS] Docker worker cannot access the repository git root." >&2
      echo "[OPAS] Recreate the laravel container so the $(auto_coding_container_repository_path) mount is applied." >&2
      echo "[OPAS] Run: docker compose up -d --force-recreate laravel" >&2
      exit 1
    fi
  fi

  if ! local_laravel_target_ready; then
    echo "[OPAS] Laravel app is not reachable on http://127.0.0.1:${LARAVEL_PORT}." >&2
    echo "[OPAS] Start the local app before opening ngrok." >&2
    echo "[OPAS] Run: scripts/start-local.sh" >&2
    exit 1
  fi
}

show_status() {
  local pid
  local https_url
  local worker_pid

  pid="$(resolve_ngrok_pid || true)"
  https_url="$(extract_https_url)"
  worker_pid="$(read_pid "$WORKER_PID_FILE" || true)"

  if [[ -n "$pid" ]] && ngrok_running "$pid"; then
    echo "[OPAS] ngrok status: running"
  elif ngrok_tunnel_available; then
    echo "[OPAS] ngrok status: running"
  else
    echo "[OPAS] ngrok status: stopped"
  fi

  if [[ -n "$https_url" ]]; then
    echo "[OPAS] Public HTTPS URL: available"
  else
    echo "[OPAS] Public HTTPS URL: unavailable"
  fi

  if [[ -n "$worker_pid" ]] && process_running "$worker_pid"; then
    echo "[OPAS] Auto-coding worker: running"
  else
    echo "[OPAS] Auto-coding worker: stopped"
  fi

  echo "[OPAS] Telegram language: $(telegram_locale_upper)"
}

for arg in "$@"; do
  case "$arg" in
    start)
      MODE="start"
      ;;
    status)
      MODE="status"
      ;;
    stop)
      MODE="stop"
      ;;
    --port=*)
      LARAVEL_PORT="${arg#*=}"
      ;;
    --lang=*)
      TELEGRAM_LOCALE="${arg#*=}"
      LOCALE_WAS_SET=1
      ;;
    --drop-pending-updates)
      DROP_PENDING_UPDATES=1
      ;;
    --status)
      MODE="status"
      ;;
    --stop)
      MODE="stop"
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $arg" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ $LOCALE_WAS_SET -eq 0 ]]; then
  TELEGRAM_LOCALE="$(normalize_locale "${TELEGRAM_LOCALE:-en}")"
fi

TELEGRAM_LOCALE="$(normalize_locale "$TELEGRAM_LOCALE")"

if [[ $# -eq 0 ]]; then
  show_interactive_menu
fi

if [[ ! -x "$SCRIPT_DIR/setup-telegram-webhook.sh" ]]; then
  echo "[OPAS] setup-telegram-webhook.sh is not executable. Fixing it now."
  chmod +x "$SCRIPT_DIR/setup-telegram-webhook.sh"
fi

case "$MODE" in
  status)
    require_command curl
    require_command php
    show_status
    exit 0
    ;;
  stop)
    stop_existing_ngrok
    stop_existing_worker
    echo "[OPAS] Telegram tunnel and auto-coding worker stopped."
    exit 0
    ;;
esac

require_command curl
require_command php

stop_existing_ngrok
stop_existing_worker
run_step "Verify Docker and Laravel target" "verify-runtime.log" ensure_local_runtime_ready
run_step "Bootstrap Telegram bot config" "bootstrap-telegram-bot.log" bootstrap_telegram_bot_from_env
run_step "Persist Telegram locale" "persist-telegram-locale.log" persist_telegram_locale
run_step "Validate Telegram bot config" "validate-telegram-bot.log" validate_telegram_bot_runtime
run_step "Check or install ngrok" "install-ngrok.log" install_ngrok_if_missing
require_command "$NGROK_BIN"
run_step "Start auto-coding worker" "start-auto-coding-worker.log" start_auto_coding_worker
run_step "Start ngrok tunnel" "start-ngrok.log" start_ngrok

NGROK_PID="$(read_pid || true)"

if [[ -z "$NGROK_PID" ]]; then
  echo "[OPAS] ngrok process id was not recorded." >&2
  exit 1
fi

run_step "Wait for public HTTPS tunnel" "wait-tunnel.log" wait_for_tunnel_to_file "$NGROK_PID"
run_step "Register Telegram webhook" "register-webhook.log" register_webhook_from_tunnel

print_start_summary
