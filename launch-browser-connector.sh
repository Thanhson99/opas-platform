#!/usr/bin/env bash
# =========================================
# macOS Puppeteer Auto Setup + Chromium Launcher
# =========================================
# Auto-installs Chromium if missing (via Homebrew)
# Opens a remote debugging instance on a free port
# Connects Puppeteer automatically for automation
#
# Optional ENV:
#   PORTS="9222 9333 9444"    # ports to try
#   PROFILE="Default"         # Chrome profile
#   AUTO_INSTALL=1            # auto install Chromium
# =========================================

set -euo pipefail

# ========= CONFIG =========
CONFIG_DIR="${HOME}/.puppetbind"
mkdir -p "${CONFIG_DIR}"

PROFILE="${PROFILE:-Default}"
PORTS="${PORTS:-9222 9333 9444 9555}"
AUTO_INSTALL="${AUTO_INSTALL:-1}"
REMOTE_HOST="127.0.0.1"
USER_DIR="${CONFIG_DIR}/profiles/chromium"
mkdir -p "${USER_DIR}/${PROFILE}"

# ========= HELPERS =========
cmd_exists() { command -v "$1" >/dev/null 2>&1; }

free_port() {
  local p="$1"
  if lsof -i :"$p" -sTCP:LISTEN >/dev/null 2>&1; then return 1; else return 0; fi
}

wait_ws() {
  local port="$1"
  echo -n "Waiting for DevTools endpoint on :$port"
  for _ in $(seq 1 80); do
    if curl -fsS "http://${REMOTE_HOST}:${port}/json/version" | grep -q '"webSocketDebuggerUrl"'; then
      echo; return 0
    fi
    echo -n "."
    sleep 0.25
  done
  echo; return 1
}

parse_ws() {
  local port="$1"
  curl -fsS "http://${REMOTE_HOST}:${port}/json/version" 2>/dev/null | \
  python3 - <<'PY'
import sys, json
try:
  print(json.load(sys.stdin).get("webSocketDebuggerUrl", ""))
except Exception:
  print("")
PY
}

# ========= INSTALL CHROMIUM IF NEEDED =========
ensure_chromium_mac() {
  if [[ -e "/Applications/Chromium.app/Contents/MacOS/Chromium" ]]; then
    echo "✅ Chromium already installed."
    return 0
  fi
  if [[ "$AUTO_INSTALL" != "1" ]]; then
    echo "❌ Chromium not found and AUTO_INSTALL disabled."
    return 1
  fi
  if ! cmd_exists brew; then
    echo "⚠️ Homebrew not found. Install it first:"
    echo '/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"'
    exit 1
  fi
  echo "📦 Installing Chromium via Homebrew..."
  brew install --cask chromium || true
}

ensure_chromium_mac

EXE="/Applications/Chromium.app/Contents/MacOS/Chromium"
if [[ ! -x "$EXE" ]]; then
  echo "❌ Chromium executable not found at: $EXE"
  exit 1
fi

echo "🚀 Using Chromium executable: $EXE"

# ========= PICK A FREE PORT =========
PORT_CHOSEN=""
for p in $PORTS; do
  if free_port "$p"; then PORT_CHOSEN="$p"; break; fi
done
if [[ -z "$PORT_CHOSEN" ]]; then
  echo "❌ No free port found in list: $PORTS"
  exit 1
fi
echo "📡 Binding on port: $PORT_CHOSEN"

# ========= LAUNCH CHROMIUM =========
echo "🚀 Launching Chromium..."
"$EXE" \
  --remote-debugging-address="${REMOTE_HOST}" \
  --remote-debugging-port="${PORT_CHOSEN}" \
  --user-data-dir="${USER_DIR}" \
  --profile-directory="${PROFILE}" \
  --no-first-run --no-default-browser-check >/dev/null 2>&1 &

sleep 1

if ! wait_ws "${PORT_CHOSEN}"; then
  echo "❌ DevTools endpoint not available on port ${PORT_CHOSEN}"
  exit 1
fi

WS_LOCAL="$(parse_ws "${PORT_CHOSEN}")"
if [[ -z "$WS_LOCAL" ]]; then
  echo "❌ Could not parse webSocketDebuggerUrl."
  exit 1
fi

echo "✅ DevTools connected at: ${WS_LOCAL}"

# ========= INSTALL PUPPETEER CORE IF MISSING =========
if [[ ! -d "node_modules/puppeteer-core" ]]; then
  echo "📦 Installing Puppeteer Core..."
  npm install puppeteer-core --silent
fi

# ========= RUN PUPPETEER TEST =========
echo "🧠 Running Puppeteer automation..."
node <<'EOF'
const puppeteer = require('puppeteer-core');
const http = require('http');

(async () => {
  const wsURL = 'http://127.0.0.1:9222/json/version';

  // Helper to fetch DevTools endpoint
  const getEndpoint = async () => {
    return new Promise((resolve, reject) => {
      http.get(wsURL, res => {
        let data = '';
        res.on('data', chunk => (data += chunk));
        res.on('end', () => {
          try {
            const json = JSON.parse(data);
            resolve(json.webSocketDebuggerUrl);
          } catch (err) {
            reject(err);
          }
        });
      }).on('error', reject);
    });
  };

  try {
    const wsEndpoint = await getEndpoint();
    if (!wsEndpoint) throw new Error("Missing DevTools WebSocket endpoint");

    const browser = await puppeteer.connect({ browserWSEndpoint: wsEndpoint });
    const page = await browser.newPage();

    await page.goto('https://example.com', { waitUntil: 'domcontentloaded' });
    const title = await page.title();
    console.log("✅ Page title:", title);

    const data = await page.evaluate(() => ({
      title: document.title,
      links: Array.from(document.querySelectorAll('a')).map(a => a.href)
    }));

    console.log("🔍 Extracted data:", data);

    await browser.disconnect();
  } catch (err) {
    console.error("❌ Puppeteer failed:", err);
    process.exit(1);
  }
})();
EOF

echo "✅ Puppeteer automation complete."
