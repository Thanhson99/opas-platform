from typing import Any

from pydantic import BaseModel, Field, HttpUrl


class ProxyFetchRequest(BaseModel):
    target_url: HttpUrl
    method: str = Field("GET", description="Currently GET or HEAD only.")
    headers: dict[str, str] = Field(default_factory=dict)
    query: dict[str, str] = Field(default_factory=dict)
    timeout: float | None = Field(None, ge=1, le=120)
    max_body_chars: int = Field(4000, ge=0, le=20000)


class ProxyFetchResponse(BaseModel):
    ok: bool
    status_code: int
    final_url: str
    content_type: str
    body_preview: str
    response_headers: dict[str, Any]
    via_proxy: bool
    proxy_configured: bool


class ProxyHealthResponse(BaseModel):
    ok: bool
    proxy_configured: bool
    allowed_hosts: list[str]
