from fastapi import APIRouter

from tool_cn_proxy_gateway.router import router as china_proxy_router

router = APIRouter()

router.include_router(china_proxy_router, prefix="/china-proxy", tags=["china-proxy"])

# ---- Douyin route ----
@router.get("/douyin/hello")
def douyin_hello():
    return {"message": "Douyin Downloader service is running"}

# ---- AI Caption route ----
@router.get("/caption/hello")
def caption_hello():
    return {"message": "AI Video Caption service is running"}

# ---- Trending route ----
@router.get("/trending/hello")
def trending_hello():
    return {"message": "Trending Keywords service is running"}
