#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOUYIN_DIR="$ROOT_DIR/services/douyin-worker"
YOUTUBE_DIR="$ROOT_DIR/services/youtube-uploader"
VIDEO_DIR="$DOUYIN_DIR/storage/videos"
METADATA_DIR="$DOUYIN_DIR/storage/metadata"

KEYWORD=""
LIMIT="10"
PRIVACY="private"
HASHTAGS="#shorts,#viral"
PLAYLIST=""
MADE_FOR_KIDS="no"
QUALITY="best"
NETWORK_WAIT_MS="15000"
SLOW_START_MS="6000"
WORKER_PORT="${DOUYIN_WORKER_PORT:-3101}"
DELETE_ORPHAN_JSON="0"
NO_UPLOAD="0"
KEEP_EXISTING_RECORDS="0"

usage() {
  cat <<'USAGE'
Usage:
  scripts/douyin-youtube-once.sh --keyword "funny dance" [options]

Options:
  --keyword TEXT          Keyword used for Douyin search. Required.
  --limit NUMBER          Number of videos to crawl/download. Default: 10.
  --privacy VALUE         YouTube privacy: private, unlisted, public. Default: private.
  --hashtags TEXT         Comma-separated hashtags. Default: #shorts,#viral.
  --playlist IDS          Comma-separated YouTube playlist IDs.
  --made-for-kids VALUE   yes or no. Default: no.
  --quality VALUE         best or balanced. Default: best.
  --network-wait-ms MS    Media network wait per video. Default: 15000.
  --slow-start-ms MS      Search page warmup wait. Default: 6000.
  --delete-orphan-json    Delete metadata JSON files that have no matching video.
  --keep-existing-records Keep existing videos, metadata, and crawl output before this run.
  --no-upload             Crawl/download/review only, then exit before the upload prompt.
  --help                  Show this help.

The script downloads first, prints a review of matched video/json pairs, then asks
before uploading. Batch upload always requires matching metadata JSON.
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --keyword)
      KEYWORD="${2:-}"
      shift 2
      ;;
    --limit)
      LIMIT="${2:-}"
      shift 2
      ;;
    --privacy)
      PRIVACY="${2:-}"
      shift 2
      ;;
    --hashtags)
      HASHTAGS="${2:-}"
      shift 2
      ;;
    --playlist)
      PLAYLIST="${2:-}"
      shift 2
      ;;
    --made-for-kids)
      MADE_FOR_KIDS="${2:-}"
      shift 2
      ;;
    --quality)
      QUALITY="${2:-}"
      shift 2
      ;;
    --network-wait-ms)
      NETWORK_WAIT_MS="${2:-}"
      shift 2
      ;;
    --slow-start-ms)
      SLOW_START_MS="${2:-}"
      shift 2
      ;;
    --delete-orphan-json)
      DELETE_ORPHAN_JSON="1"
      shift
      ;;
    --keep-existing-records)
      KEEP_EXISTING_RECORDS="1"
      shift
      ;;
    --no-upload)
      NO_UPLOAD="1"
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [[ -z "$KEYWORD" ]]; then
  echo "Missing --keyword." >&2
  usage >&2
  exit 1
fi

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

read_env_value() {
  local file="$1"
  local key="$2"

  if [[ ! -f "$file" ]]; then
    return 0
  fi

  awk -F '=' -v key="$key" '
    $1 == key {
      value = substr($0, length(key) + 2)
      gsub(/^["'\'']|["'\'']$/, "", value)
      print value
      exit
    }
  ' "$file"
}

require_command curl
require_command node
require_command npm

if [[ ! -d "$DOUYIN_DIR" ]]; then
  echo "Missing service implementation: $DOUYIN_DIR" >&2
  echo "Add the Douyin worker service before running this workflow." >&2
  exit 1
fi

if [[ ! -d "$YOUTUBE_DIR" ]]; then
  echo "Missing service implementation: $YOUTUBE_DIR" >&2
  echo "Add the YouTube uploader service before running this workflow." >&2
  exit 1
fi

if [[ "$KEEP_EXISTING_RECORDS" != "1" ]]; then
  echo "Deleting old local Douyin videos, metadata, and crawl output before this run..."
  find "$VIDEO_DIR" -maxdepth 1 -type f -name '*.mp4' -delete
  find "$METADATA_DIR" -maxdepth 1 -type f -name '*.json' -delete
  find "$DOUYIN_DIR/storage/output" -maxdepth 1 -type f \( -name '*.json' -o -name '*.csv' \) -delete
fi

DOUYIN_WORKER_API_KEY="${DOUYIN_WORKER_API_KEY:-$(read_env_value "$DOUYIN_DIR/.env" "DOUYIN_WORKER_API_KEY")}"
DOUYIN_WORKER_API_KEY="${DOUYIN_WORKER_API_KEY:-change_me_local_secret}"

WORKER_PID=""

cleanup() {
  if [[ -n "$WORKER_PID" ]]; then
    kill "$WORKER_PID" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

worker_health_url="http://127.0.0.1:$WORKER_PORT/health"
worker_api_url="http://127.0.0.1:$WORKER_PORT/api/douyin/crawl-and-download"

if curl -fsS "$worker_health_url" >/dev/null 2>&1; then
  echo "Douyin worker is already running on port $WORKER_PORT."
else
  if [[ ! -d "$DOUYIN_DIR/node_modules" ]]; then
    echo "Missing $DOUYIN_DIR/node_modules. Run: cd services/douyin-worker && npm install" >&2
    exit 1
  fi

  echo "Starting Douyin worker on port $WORKER_PORT..."
  (cd "$DOUYIN_DIR" && npm run dev) &
  WORKER_PID="$!"

  for _ in $(seq 1 60); do
    if curl -fsS "$worker_health_url" >/dev/null 2>&1; then
      break
    fi

    if ! kill -0 "$WORKER_PID" >/dev/null 2>&1; then
      wait "$WORKER_PID" || true
      echo "Douyin worker exited before it became ready. Run manually: cd services/douyin-worker && npm run dev" >&2
      exit 1
    fi

    sleep 1
  done

  if ! curl -fsS "$worker_health_url" >/dev/null 2>&1; then
    echo "Douyin worker did not become ready on port $WORKER_PORT." >&2
    exit 1
  fi
fi

export KEYWORD LIMIT QUALITY NETWORK_WAIT_MS SLOW_START_MS
payload="$(
  node -e '
    const number = (value, fallback) => {
      const parsed = Number.parseInt(String(value || ""), 10);
      return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
    };

    console.log(JSON.stringify({
      keyword: process.env.KEYWORD,
      limit: number(process.env.LIMIT, 10),
      download: true,
      quality: process.env.QUALITY === "balanced" ? "balanced" : "best",
      networkWaitMs: number(process.env.NETWORK_WAIT_MS, 15000),
      slowStartMs: number(process.env.SLOW_START_MS, 6000)
    }));
  '
)"

response_file="$(mktemp "${TMPDIR:-/tmp}/douyin-crawl-download.XXXXXX")"

echo "Crawling and downloading keyword: $KEYWORD"
curl -fsS \
  -X POST "$worker_api_url" \
  -H "Content-Type: application/json" \
  -H "x-api-key: $DOUYIN_WORKER_API_KEY" \
  -d "$payload" \
  -o "$response_file"

echo "Crawl/download response saved to: $response_file"

export VIDEO_DIR METADATA_DIR DELETE_ORPHAN_JSON
set +e
node <<'NODE'
const fs = require('node:fs');
const path = require('node:path');

const videoDir = process.env.VIDEO_DIR;
const metadataDir = process.env.METADATA_DIR;
const shouldDeleteOrphanJson = process.env.DELETE_ORPHAN_JSON === '1';

function listFiles(dir, suffix) {
  if (!fs.existsSync(dir)) {
    return [];
  }

  return fs.readdirSync(dir)
    .filter((file) => file.toLowerCase().endsWith(suffix))
    .sort((left, right) => left.localeCompare(right));
}

function readMetadata(file) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (error) {
    return { __error: error instanceof Error ? error.message : String(error) };
  }
}

const videos = listFiles(videoDir, '.mp4');
const metadataFiles = listFiles(metadataDir, '.json');
const videoIds = new Set(videos.map((file) => path.basename(file, path.extname(file))));
const metadataIds = new Set(metadataFiles.map((file) => path.basename(file, path.extname(file))));
const invalidJsonIds = [];
const matchedIds = [...videoIds]
  .filter((id) => {
    if (!metadataIds.has(id)) {
      return false;
    }

    const metadata = readMetadata(path.join(metadataDir, `${id}.json`));

    if (metadata.__error) {
      invalidJsonIds.push(id);
      return false;
    }

    return true;
  })
  .sort((left, right) => left.localeCompare(right));
const videosWithoutJson = [...videoIds].filter((id) => !metadataIds.has(id)).sort((left, right) => left.localeCompare(right));
const jsonWithoutVideos = [...metadataIds].filter((id) => !videoIds.has(id)).sort((left, right) => left.localeCompare(right));

console.log('\nReview before upload');
console.log('====================');
console.log(`Matched video/json pairs: ${matchedIds.length}`);

for (const id of matchedIds) {
  const videoPath = path.join(videoDir, `${id}.mp4`);
  const metadataPath = path.join(metadataDir, `${id}.json`);
  const metadata = readMetadata(metadataPath);
  const sizeMb = fs.statSync(videoPath).size / 1024 / 1024;
  const title = metadata.translatedTitle || metadata.title || '(missing title)';
  const hashtags = Array.isArray(metadata.hashtags) ? metadata.hashtags.join(', ') : '';
  const tags = Array.isArray(metadata.tags) ? metadata.tags.join(', ') : '';

  console.log(`\n- ${id}`);
  console.log(`  video: ${videoPath} (${sizeMb.toFixed(1)} MB)`);
  console.log(`  json:  ${metadataPath}`);
  console.log(`  title: ${title}`);
  if (hashtags) console.log(`  hashtags: ${hashtags}`);
  if (tags) console.log(`  tags: ${tags}`);
  if (metadata.sourceUrl) console.log(`  source: ${metadata.sourceUrl}`);
  if (metadata.__error) console.log(`  json_error: ${metadata.__error}`);
}

if (videosWithoutJson.length > 0) {
  console.log('\nVideos skipped because metadata JSON is missing:');
  for (const id of videosWithoutJson) {
    console.log(`- ${path.join(videoDir, `${id}.mp4`)}`);
  }
}

if (invalidJsonIds.length > 0) {
  console.log('\nVideos skipped because metadata JSON is invalid:');
  for (const id of invalidJsonIds) {
    const metadataPath = path.join(metadataDir, `${id}.json`);
    const metadata = readMetadata(metadataPath);
    console.log(`- ${path.join(videoDir, `${id}.mp4`)}`);
    console.log(`  json: ${metadataPath}`);
    console.log(`  error: ${metadata.__error}`);
  }
}

if (jsonWithoutVideos.length > 0) {
  console.log('\nMetadata JSON ignored because matching video is missing:');
  for (const id of jsonWithoutVideos) {
    const metadataPath = path.join(metadataDir, `${id}.json`);
    console.log(`- ${metadataPath}`);
    if (shouldDeleteOrphanJson) {
      fs.rmSync(metadataPath, { force: true });
      console.log(`  deleted`);
    }
  }
}

if (matchedIds.length === 0) {
  console.log('\nNo uploadable pairs found.');
  process.exitCode = 2;
}
NODE

review_status="$?"
set -e
if [[ "$review_status" -eq 2 ]]; then
  exit 0
fi

if [[ "$review_status" -ne 0 ]]; then
  exit "$review_status"
fi

if [[ "$NO_UPLOAD" == "1" ]]; then
  echo
  echo "Upload skipped because --no-upload was provided."
  exit 0
fi

echo
echo "Applying title language policy"
echo "=============================="

export VIDEO_DIR METADATA_DIR
node <<'NODE'
const fs = require('node:fs');
const path = require('node:path');

const videoDir = process.env.VIDEO_DIR;
const metadataDir = process.env.METADATA_DIR;
const libreTranslateUrl = process.env.LIBRETRANSLATE_URL || 'http://localhost:5001';

function listMatchedPairs() {
  if (!fs.existsSync(videoDir) || !fs.existsSync(metadataDir)) {
    return [];
  }

  return fs.readdirSync(videoDir)
    .filter((file) => file.toLowerCase().endsWith('.mp4'))
    .map((file) => {
      const id = path.basename(file, path.extname(file));
      const videoPath = path.join(videoDir, file);
      const metadataPath = path.join(metadataDir, `${id}.json`);

      return { id, videoPath, metadataPath, mtimeMs: fs.statSync(videoPath).mtimeMs };
    })
    .filter((pair) => fs.existsSync(pair.metadataPath))
    .sort((left, right) => left.mtimeMs - right.mtimeMs || left.id.localeCompare(right.id));
}

async function translate(text, target) {
  if (!text) {
    return '';
  }

  if (target === 'vi') {
    const english = await requestTranslate(text, 'auto', 'en');

    return requestTranslate(english || text, 'en', 'vi');
  }

  return requestTranslate(text, 'auto', target);
}

async function requestTranslate(text, source, target) {
  const response = await fetch(`${libreTranslateUrl}/translate`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ q: text, source, target, format: 'text' }),
  });

  if (!response.ok) {
    throw new Error(`LibreTranslate failed with HTTP ${response.status}`);
  }

  const payload = await response.json();

  return typeof payload.translatedText === 'string' ? payload.translatedText.trim() : '';
}

async function titleFor(metadata, index) {
  const originalTitle = typeof metadata.title === 'string' ? metadata.title.trim() : '';
  const translatedTitle = typeof metadata.translatedTitle === 'string' ? metadata.translatedTitle.trim() : '';

  if (index < 2) {
    return { language: 'en', title: await translate(originalTitle, 'en') };
  }

  if (index < 4) {
    return { language: 'zh', title: originalTitle };
  }

  return { language: 'vi', title: translatedTitle || (await translate(originalTitle, 'vi')) };
}

async function main() {
  const pairs = listMatchedPairs();

  for (let index = 0; index < pairs.length; index += 1) {
    const pair = pairs[index];
    const metadata = JSON.parse(fs.readFileSync(pair.metadataPath, 'utf8'));
    const titlePolicy = await titleFor(metadata, index);

    metadata.uploadTitle = titlePolicy.title || metadata.title || pair.id;
    metadata.uploadTitleLanguage = titlePolicy.language;
    fs.writeFileSync(pair.metadataPath, `${JSON.stringify(metadata, null, 2)}\n`);
    console.log(`${index + 1}. ${pair.id} | ${titlePolicy.language} | ${metadata.uploadTitle}`);
  }
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : String(error));
  process.exitCode = 1;
});
NODE

node <<'NODE'
const fs = require('node:fs');
const path = require('node:path');

const videoDir = process.env.VIDEO_DIR;
const metadataDir = process.env.METADATA_DIR;

const rows = fs.readdirSync(videoDir)
  .filter((file) => file.toLowerCase().endsWith('.mp4'))
  .map((file) => {
    const id = path.basename(file, path.extname(file));
    const videoPath = path.join(videoDir, file);
    const metadataPath = path.join(metadataDir, `${id}.json`);

    return { id, videoPath, metadataPath, mtimeMs: fs.statSync(videoPath).mtimeMs };
  })
  .filter((row) => fs.existsSync(row.metadataPath))
  .sort((left, right) => left.mtimeMs - right.mtimeMs || left.id.localeCompare(right.id));

console.log('\nUpload titles after language policy:');
rows.forEach((row, index) => {
  const metadata = JSON.parse(fs.readFileSync(row.metadataPath, 'utf8'));
  const language = metadata.uploadTitleLanguage || 'unknown';
  const title = metadata.uploadTitle || metadata.translatedTitle || metadata.title || '(missing title)';

  console.log(`${index + 1}. ${row.id} | ${language} | ${title}`);
});
NODE

echo
echo "Review the JSON title/hashtags and open any video path above if needed."
read -r -p "Upload matched pairs to YouTube now? Type yes to upload: " upload_confirm

if [[ "$upload_confirm" != "yes" ]]; then
  echo "Upload cancelled."
  exit 0
fi

if [[ ! -d "$YOUTUBE_DIR/node_modules" ]]; then
  echo "Missing $YOUTUBE_DIR/node_modules. Run: cd services/youtube-uploader && npm install" >&2
  exit 1
fi

upload_args=(-- --dir "$VIDEO_DIR" --privacy "$PRIVACY" --hashtags "$HASHTAGS" --made-for-kids "$MADE_FOR_KIDS" --require-metadata --delete-local-after-upload)

# Future Laravel control should resolve keyword -> playlistId from
# services/youtube-uploader/config/playlist-routing.json instead of passing --playlist manually.
if [[ -n "$PLAYLIST" ]]; then
  upload_args+=(--playlist "$PLAYLIST")
fi

echo "Uploading matched video/json pairs to YouTube..."
(cd "$YOUTUBE_DIR" && npm run upload:batch "${upload_args[@]}")

echo "Deleting crawl output JSON/CSV after successful upload..."
find "$DOUYIN_DIR/storage/output" -maxdepth 1 -type f \( -name '*.json' -o -name '*.csv' \) -delete
