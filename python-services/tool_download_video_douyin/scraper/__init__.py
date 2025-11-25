from typing import Literal
from .base import ScraperProtocol
from ...shared_imports import settings

def get_scraper() -> ScraperProtocol:
    backend: Literal["playwright","selenium"] = settings.TOOL_DOUYIN_BACKEND.lower()  # type: ignore
    if backend == "selenium":
        from .selenium_impl import SeleniumScraper
        return SeleniumScraper()
    else:
        from .playwright_impl import PlaywrightScraper
        return PlaywrightScraper()
