# Python Services

```text
python-services/
|
+-- main.py
+-- router.py
+-- Dockerfile
+-- README.md
+-- init_python_services.sh
+-- tool_download_video_douyin/
|   +-- main.py
|   +-- requirements.txt
|   +-- README.md
|   +-- __init__.py
|   +-- downloader/
|   +-- models/
|   +-- scraper/
|   +-- services/
|   +-- utils/
|   `-- views/
+-- tool_ai_video_caption/
|   +-- main.py
|   +-- requirements.txt
|   +-- README.md
|   `-- __init__.py
+-- tool_cn_proxy_gateway/
|   +-- main.py
|   +-- router.py
|   +-- service.py
|   +-- config.py
|   +-- models.py
|   +-- requirements.txt
|   +-- README.md
|   `-- __init__.py
+-- tool_trending_keywords/
|   +-- main.py
|   +-- requirements.txt
|   +-- README.md
|   `-- __init__.py
`-- shared-libs/
    +-- __init__.py
    +-- config/
    `-- utils/
```

## Notes

- `tool_download_video_douyin`: service for crawling and downloading Douyin videos.
- `tool_ai_video_caption`: service for generating video captions.
- `tool_cn_proxy_gateway`: service that forwards requests through an upstream China proxy for Douyin.
- `tool_trending_keywords`: service for collecting trending keywords.
- `shared-libs`: shared libraries used by the Python services.
