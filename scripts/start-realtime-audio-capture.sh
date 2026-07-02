#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVICE_DIR="$ROOT_DIR/services/python"
LOG_DIR="$ROOT_DIR/.codex-tmp"
LOG_FILE="$LOG_DIR/realtime-audio-capture.log"
BROWSER_LOG_FILE="$LOG_DIR/realtime-audio-browser.log"
EXTENSION_DIR="$ROOT_DIR/apps/realtime-audio-extension"
BROWSER_PROFILE_DIR="${OPAS_REALTIME_AUDIO_BROWSER_PROFILE:-$LOG_DIR/realtime-audio-browser-profile}"
BROWSER_APP="${OPAS_REALTIME_AUDIO_BROWSER_APP:-Google Chrome}"
BROWSER_MODE="${OPAS_REALTIME_AUDIO_BROWSER_MODE:-existing}"
PORT="${OPAS_REALTIME_AUDIO_PORT:-5010}"
HOST="${OPAS_REALTIME_AUDIO_HOST:-127.0.0.1}"
APP_URL="${OPAS_REALTIME_AUDIO_START_URL:-http://${HOST}:${PORT}/realtime-audio/ui}"
HEALTH_URL="http://${HOST}:${PORT}/realtime-audio/health"
OPEN_BROWSER="${OPAS_REALTIME_AUDIO_OPEN_BROWSER:-0}"
INSTALL_STT=1
SERVER_PID=""

export KMP_DUPLICATE_LIB_OK="${KMP_DUPLICATE_LIB_OK:-TRUE}"

for arg in "$@"; do
  case "$arg" in
    --no-open)
      OPEN_BROWSER=0
      ;;
    --open-browser)
      OPEN_BROWSER=1
      ;;
    --managed-browser)
      BROWSER_MODE="managed"
      OPEN_BROWSER=1
      ;;
    --port=*)
      PORT="${arg#--port=}"
      APP_URL="${OPAS_REALTIME_AUDIO_START_URL:-http://${HOST}:${PORT}/realtime-audio/ui}"
      HEALTH_URL="http://${HOST}:${PORT}/realtime-audio/health"
      ;;
    --with-stt)
      INSTALL_STT=1
      ;;
    --capture-only)
      INSTALL_STT=0
      ;;
    *)
      echo "[OPAS] Unknown argument: $arg"
      echo "[OPAS] Usage: scripts/start-realtime-audio-capture.sh [--open-browser] [--no-open] [--managed-browser] [--port=5010] [--with-stt] [--capture-only]"
      exit 1
      ;;
  esac
done

mkdir -p "$LOG_DIR"
: > "$LOG_FILE"

cleanup() {
  local exit_code=$?

  if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" >/dev/null 2>&1 || true
  fi

  exit "$exit_code"
}

trap cleanup INT TERM EXIT

show_spinner() {
  local pid="$1"
  local message="$2"
  local frames='|/-\'
  local index=0

  while kill -0 "$pid" 2>/dev/null; do
    printf '\r[OPAS] %s %c' "$message" "${frames:index++%${#frames}:1}"
    sleep 0.12
  done

  wait "$pid"
  local exit_code=$?
  if [[ $exit_code -ne 0 ]]; then
    printf '\r[OPAS] %s failed\n' "$message"
    echo "[OPAS] Details were written to: $LOG_FILE"
    exit "$exit_code"
  fi

  printf '\r[OPAS] %s done\n' "$message"
}

run_step() {
  local message="$1"
  shift

  (
    "$@"
  ) >>"$LOG_FILE" 2>&1 &

  show_spinner "$!" "$message"
}

wait_for_health() {
  local attempts=30

  for _ in $(seq 1 "$attempts"); do
    if curl --silent --fail --max-time 2 "$HEALTH_URL" >/dev/null 2>&1; then
      echo "[OPAS] Realtime audio capture service is ready"
      return 0
    fi

    sleep 1
  done

  echo "[OPAS] Realtime audio capture service did not become ready in time"
  echo "[OPAS] Details were written to: $LOG_FILE"
  return 1
}

open_ui() {
  if [[ "$OPEN_BROWSER" != "1" ]]; then
    return 0
  fi

  if [[ "$BROWSER_MODE" == "managed" ]]; then
    if open_managed_extension_browser; then
      return 0
    fi

    echo "[OPAS] Could not find a Chromium browser for managed extension loading; falling back to the existing browser"
  fi

  if open_existing_browser; then
    return 0
  fi

  echo "[OPAS] Could not open the configured browser; falling back to the OS default browser"
  if command -v open >/dev/null 2>&1; then
    open "$APP_URL" >/dev/null 2>&1 || true
  elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$APP_URL" >/dev/null 2>&1 || true
  fi
}

open_existing_browser() {
  if command -v open >/dev/null 2>&1; then
    open -a "$BROWSER_APP" "$APP_URL" >/dev/null 2>&1 && {
      echo "[OPAS] Opened existing browser app: $BROWSER_APP"
      return 0
    }
  fi

  local browser_executable
  browser_executable="$(find_chromium_browser)"
  if [[ -z "$browser_executable" ]]; then
    return 1
  fi

  "$browser_executable" "$APP_URL" >>"$BROWSER_LOG_FILE" 2>&1 &
  echo "[OPAS] Opened existing Chromium profile"
}

open_managed_extension_browser() {
  if [[ ! -d "$EXTENSION_DIR" ]]; then
    echo "[OPAS] Extension directory was not found: $EXTENSION_DIR"
    return 1
  fi

  local browser_executable
  browser_executable="$(find_chromium_browser)"
  if [[ -z "$browser_executable" ]]; then
    return 1
  fi

  mkdir -p "$BROWSER_PROFILE_DIR"
  : > "$BROWSER_LOG_FILE"

  "$browser_executable" \
    --user-data-dir="$BROWSER_PROFILE_DIR" \
    --no-first-run \
    --no-default-browser-check \
    --disable-extensions-except="$EXTENSION_DIR" \
    --load-extension="$EXTENSION_DIR" \
    "$APP_URL" >>"$BROWSER_LOG_FILE" 2>&1 &

  echo "[OPAS] Opened a Chromium profile with the realtime audio extension loaded"
}

find_chromium_browser() {
  if [[ -n "${OPAS_REALTIME_AUDIO_BROWSER:-}" && -x "$OPAS_REALTIME_AUDIO_BROWSER" ]]; then
    printf '%s\n' "$OPAS_REALTIME_AUDIO_BROWSER"
    return 0
  fi

  local candidates=(
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
    "/Applications/Chromium.app/Contents/MacOS/Chromium"
    "/Applications/Brave Browser.app/Contents/MacOS/Brave Browser"
    "/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge"
    "/Applications/CocCoc.app/Contents/MacOS/CocCoc"
  )

  local candidate
  for candidate in "${candidates[@]}"; do
    if [[ -x "$candidate" ]]; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done

  local commands=(google-chrome chromium chromium-browser brave-browser microsoft-edge)
  for candidate in "${commands[@]}"; do
    if command -v "$candidate" >/dev/null 2>&1; then
      command -v "$candidate"
      return 0
    fi
  done

  return 1
}

echo "[OPAS] Preparing realtime audio capture service..."

if ! command -v python3 >/dev/null 2>&1; then
  echo "[OPAS] python3 is required but was not found"
  exit 1
fi

if curl --silent --fail --max-time 2 "$HEALTH_URL" >/dev/null 2>&1; then
  echo "[OPAS] Existing realtime audio capture server detected"
  if [[ "$INSTALL_STT" -eq 1 ]]; then
    echo "[OPAS] Stop the existing server, then rerun this script to ensure local STT dependencies are loaded"
  fi
  open_ui
  echo
  echo "[OPAS] API: http://${HOST}:${PORT}/realtime-audio"
  echo "[OPAS] Browser app: $BROWSER_APP"
  if [[ "$BROWSER_MODE" == "managed" ]]; then
    echo "[OPAS] Browser profile: $BROWSER_PROFILE_DIR"
    echo "[OPAS] Browser log:     $BROWSER_LOG_FILE"
  fi
  echo "[OPAS] Existing server is still running. Stop it from its original terminal when needed."
  exit 0
fi

run_step "Installing Python dependencies" python3 -m pip install -q -r "$SERVICE_DIR/tool_realtime_audio_capture/requirements.txt"
if [[ "$INSTALL_STT" -eq 1 ]]; then
  run_step "Installing local STT dependencies" python3 -m pip install -q -r "$SERVICE_DIR/tool_realtime_audio_capture/requirements-stt.txt"
else
  echo "[OPAS] Local STT dependency install skipped (--capture-only)"
fi

echo "[OPAS] Starting realtime audio capture server..."
(
  cd "$SERVICE_DIR"
  python3 -m uvicorn tool_realtime_audio_capture.main:app --host "$HOST" --port "$PORT"
) >>"$LOG_FILE" 2>&1 &
SERVER_PID=$!

wait_for_health
open_ui

echo
echo "[OPAS] Realtime audio capture service is running"
echo "[OPAS] API:  http://${HOST}:${PORT}/realtime-audio"
echo "[OPAS] Log:  $LOG_FILE"
echo "[OPAS] Browser app: $BROWSER_APP"
if [[ "$BROWSER_MODE" == "managed" ]]; then
  echo "[OPAS] Browser profile: $BROWSER_PROFILE_DIR"
  echo "[OPAS] Browser log:     $BROWSER_LOG_FILE"
  echo "[OPAS] Open the tab you want to capture in this browser window, then click the extension icon."
fi
echo "[OPAS] Web UI: http://${HOST}:${PORT}/realtime-audio/ui"
echo "[OPAS] Click the extension icon and press Open Web if the web UI is not open."
echo "[OPAS] Press Ctrl+C to stop"

wait "$SERVER_PID"
