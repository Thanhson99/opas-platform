#!/bin/bash

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"

# --------------------------
# Service 1 - Douyin downloader
# --------------------------
mkdir -p "$BASE_DIR"/tool-download-video-douyin/{downloader,scraper,utils,views}
touch "$BASE_DIR"/tool-download-video-douyin/{main.py,requirements.txt,README.md,__init__.py}

# --------------------------
# Service 2 - AI caption generator
# --------------------------
mkdir -p "$BASE_DIR"/tool-ai-video-caption/{caption_generator,utils}
touch "$BASE_DIR"/tool-ai-video-caption/{main.py,requirements.txt,README.md,__init__.py}

# --------------------------
# Service 3 - Crawl trending keywords
# --------------------------
mkdir -p "$BASE_DIR"/tool-trending-keywords/{scraper,utils}
touch "$BASE_DIR"/tool-trending-keywords/{main.py,requirements.txt,README.md,__init__.py}

# --------------------------
# Shared libraries
# --------------------------
mkdir -p "$BASE_DIR"/shared-libs
touch "$BASE_DIR"/shared-libs/{logger.py,http_client.py,config.py,constants.py,__init__.py}

echo "✅ Folder structure created successfully!"
