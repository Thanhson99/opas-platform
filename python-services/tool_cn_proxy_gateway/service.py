from urllib.parse import urlparse

import requests
from fastapi import HTTPException

from .config import settings
from .models import ProxyFetchRequest, ProxyFetchResponse, ProxyHealthResponse


USER_AGENT = (
    "Mozilla/5.0 (Linux; Android 13; Pixel 7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Mobile Safari/537.36"
)


def get_health() -> ProxyHealthResponse:
    return ProxyHealthResponse(
        ok=True,
        proxy_configured=bool(settings.china_proxy_url),
        allowed_hosts=sorted(settings.allowed_hosts),
    )


def fetch_via_proxy(req: ProxyFetchRequest) -> ProxyFetchResponse:
    method = req.method.upper()
    if method not in {"GET", "HEAD"}:
        raise HTTPException(status_code=400, detail="Only GET and HEAD are supported.")

    parsed = urlparse(str(req.target_url))
    hostname = (parsed.hostname or "").lower()

    if hostname not in settings.allowed_hosts:
        raise HTTPException(
            status_code=400,
            detail=f"Host '{hostname}' is not in CHINA_PROXY_ALLOWED_HOSTS.",
        )

    headers = {
        "User-Agent": USER_AGENT,
        "Accept-Language": "zh-CN,zh;q=0.9,en;q=0.8",
        **req.headers,
    }

    proxies = None
    if settings.china_proxy_url:
        proxies = {
            "http": settings.china_proxy_url,
            "https": settings.china_proxy_url,
        }

    try:
        response = requests.request(
            method=method,
            url=str(req.target_url),
            headers=headers,
            params=req.query,
            timeout=req.timeout or settings.china_proxy_timeout,
            proxies=proxies,
            verify=settings.china_proxy_verify_ssl,
            allow_redirects=True,
        )
    except requests.RequestException as exc:
        raise HTTPException(status_code=502, detail=f"Upstream request failed: {exc}") from exc

    content_type = response.headers.get("content-type", "")
    if method == "HEAD":
        body_preview = ""
    else:
        response.encoding = response.encoding or response.apparent_encoding or "utf-8"
        body_preview = response.text[: req.max_body_chars]

    return ProxyFetchResponse(
        ok=response.ok,
        status_code=response.status_code,
        final_url=str(response.url),
        content_type=content_type,
        body_preview=body_preview,
        response_headers={
            "server": response.headers.get("server", ""),
            "content-length": response.headers.get("content-length", ""),
            "content-type": content_type,
            "set-cookie-present": "set-cookie" in {k.lower() for k in response.headers.keys()},
        },
        via_proxy=bool(proxies),
        proxy_configured=bool(settings.china_proxy_url),
    )
