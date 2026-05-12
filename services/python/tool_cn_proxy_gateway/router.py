from fastapi import APIRouter

from .models import ProxyFetchRequest, ProxyFetchResponse, ProxyHealthResponse
from .service import fetch_via_proxy, get_health

router = APIRouter()


@router.get("/hello")
def hello() -> dict[str, str]:
    return {"message": "China Proxy Gateway service is running"}


@router.get("/health", response_model=ProxyHealthResponse)
def health() -> ProxyHealthResponse:
    return get_health()


@router.post("/fetch", response_model=ProxyFetchResponse)
def fetch(req: ProxyFetchRequest) -> ProxyFetchResponse:
    return fetch_via_proxy(req)
