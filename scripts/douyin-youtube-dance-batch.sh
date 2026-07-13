#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
YOUTUBE_DIR="$ROOT_DIR/services/youtube-uploader"
VIDEO_DIR="$ROOT_DIR/services/douyin-worker/storage/videos"
METADATA_DIR="$ROOT_DIR/services/douyin-worker/storage/metadata"

LIMIT="3"
PRIVACY="private"
HASHTAGS="#shorts,#viral,#dance,#beauty,#trend"
PLAYLIST=""
MADE_FOR_KIDS="no"
DELETE_ORPHAN_JSON="1"

KEYWORDS=(
  "高颜值小姐姐 热舞"
  "美女热舞"
  "辣妹跳舞"
  "高颜值小姐姐 舞蹈"
  "小姐姐卡点舞"
  "美女变装 热舞"
)

usage() {
  cat <<'USAGE'
Usage:
  scripts/douyin-youtube-dance-batch.sh [options]

Options:
  --limit NUMBER          Videos per keyword. Default: 3.
  --privacy VALUE         YouTube privacy: private, unlisted, public. Default: private.
  --hashtags TEXT         Comma-separated hashtags.
  --playlist IDS          Comma-separated YouTube playlist IDs.
  --made-for-kids VALUE   yes or no. Default: no.
  --keep-orphan-json      Keep metadata JSON files that have no matching video.
  --help                  Show this help.

This runs the default viral dance/beauty keyword set, then asks once before upload.
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
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
    --keep-orphan-json)
      DELETE_ORPHAN_JSON="0"
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

if [[ ! -d "$ROOT_DIR/services/douyin-worker" ]]; then
  echo "Missing service implementation: $ROOT_DIR/services/douyin-worker" >&2
  echo "Add the Douyin worker service before running this workflow." >&2
  exit 1
fi

if [[ ! -d "$YOUTUBE_DIR" ]]; then
  echo "Missing service implementation: $YOUTUBE_DIR" >&2
  echo "Add the YouTube uploader service before running this workflow." >&2
  exit 1
fi

echo "Deleting old local Douyin videos, metadata, and crawl output before this batch..."
find "$VIDEO_DIR" -maxdepth 1 -type f -name '*.mp4' -delete
find "$METADATA_DIR" -maxdepth 1 -type f -name '*.json' -delete
find "$ROOT_DIR/services/douyin-worker/storage/output" -maxdepth 1 -type f \( -name '*.json' -o -name '*.csv' \) -delete

for keyword in "${KEYWORDS[@]}"; do
  echo
  echo "============================================================"
  echo "Keyword: $keyword"
  echo "============================================================"

  "$ROOT_DIR/scripts/douyin-youtube-once.sh" \
    --keyword "$keyword" \
    --limit "$LIMIT" \
    --privacy "$PRIVACY" \
    --hashtags "$HASHTAGS" \
    --keep-existing-records \
    --no-upload
done

if [[ "$DELETE_ORPHAN_JSON" == "1" ]]; then
  export VIDEO_DIR METADATA_DIR
  node <<'NODE'
const fs = require('node:fs');
const path = require('node:path');

const videoDir = process.env.VIDEO_DIR;
const metadataDir = process.env.METADATA_DIR;

if (!fs.existsSync(videoDir) || !fs.existsSync(metadataDir)) {
  process.exit(0);
}

const videoIds = new Set(
  fs.readdirSync(videoDir)
    .filter((file) => file.toLowerCase().endsWith('.mp4'))
    .map((file) => path.basename(file, path.extname(file))),
);

for (const file of fs.readdirSync(metadataDir)) {
  if (!file.toLowerCase().endsWith('.json')) {
    continue;
  }

  const id = path.basename(file, path.extname(file));

  if (!videoIds.has(id)) {
    const metadataPath = path.join(metadataDir, file);
    fs.rmSync(metadataPath, { force: true });
    console.log(`Deleted orphan metadata: ${metadataPath}`);
  }
}
NODE
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

echo
echo "Final review before upload"
echo "=========================="

export VIDEO_DIR METADATA_DIR
node <<'NODE'
const fs = require('node:fs');
const path = require('node:path');

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

const videoDir = process.env.VIDEO_DIR;
const metadataDir = process.env.METADATA_DIR;
const videos = listFiles(videoDir, '.mp4');
const metadataIds = new Set(listFiles(metadataDir, '.json').map((file) => path.basename(file, path.extname(file))));
let matchedCount = 0;

for (const file of videos) {
  const id = path.basename(file, path.extname(file));
  const metadataPath = path.join(metadataDir, `${id}.json`);

  if (!metadataIds.has(id)) {
    console.log(`SKIP missing json: ${path.join(videoDir, file)}`);
    continue;
  }

  const metadata = readMetadata(metadataPath);

  if (metadata.__error) {
    console.log(`SKIP invalid json: ${metadataPath} ${metadata.__error}`);
    continue;
  }

  const videoPath = path.join(videoDir, file);
  const sizeMb = fs.statSync(videoPath).size / 1024 / 1024;
  const title = metadata.uploadTitle || metadata.translatedTitle || metadata.title || '(missing title)';
  const language = metadata.uploadTitleLanguage || metadata.titleLanguage || 'unknown';
  matchedCount += 1;
  console.log(`${matchedCount}. ${id} | ${sizeMb.toFixed(1)} MB | ${language} | ${title}`);
}

console.log(`\nUploadable matched pairs: ${matchedCount}`);

if (matchedCount === 0) {
  process.exitCode = 2;
}
NODE

review_status="$?"
if [[ "$review_status" -eq 2 ]]; then
  echo "No uploadable videos found."
  exit 0
fi

if [[ "$review_status" -ne 0 ]]; then
  exit "$review_status"
fi

echo
read -r -p "Upload all matched videos to YouTube now? Type yes to upload: " upload_confirm

if [[ "$upload_confirm" != "yes" ]]; then
  echo "Upload cancelled."
  exit 0
fi

if [[ ! -d "$YOUTUBE_DIR/node_modules" ]]; then
  echo "Missing $YOUTUBE_DIR/node_modules. Run: cd services/youtube-uploader && npm install" >&2
  exit 1
fi

upload_args=(-- --dir "$VIDEO_DIR" --privacy "$PRIVACY" --hashtags "$HASHTAGS" --made-for-kids "$MADE_FOR_KIDS" --require-metadata --delete-local-after-upload)

if [[ -n "$PLAYLIST" ]]; then
  upload_args+=(--playlist "$PLAYLIST")
fi

(cd "$YOUTUBE_DIR" && npm run upload:batch "${upload_args[@]}")

echo "Deleting crawl output JSON/CSV after successful upload..."
find "$ROOT_DIR/services/douyin-worker/storage/output" -maxdepth 1 -type f \( -name '*.json' -o -name '*.csv' \) -delete
