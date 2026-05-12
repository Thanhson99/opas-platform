from .scraper import get_scraper
from .models import CrawlRequest, CrawlResponse
from .utils.parsers import normalize_url
from .shared_imports import settings

def crawl_links(req: CrawlRequest) -> CrawlResponse:
    scraper = get_scraper()
    try:
        scraper.open_home()
        seed = normalize_url(str(req.seed_url)) if req.seed_url else None
        links = scraper.collect_video_links(seed, scrolls=req.scrolls)
        return CrawlResponse(count=len(links), links=links, backend=settings.TOOL_DOUYIN_BACKEND)
    finally:
        scraper.shutdown()
