from typing import List, Optional
from playwright.sync_api import sync_playwright
from ..utils.selectors import is_video_link
from ...shared_imports import settings

class PlaywrightScraper:
    def __init__(self):
        self._p = sync_playwright().start()
        self._browser = self._p.chromium.launch(headless=settings.PW_HEADLESS)
        context_kwargs = {}
        if settings.PW_MOBILE_UA:
            context_kwargs.update(
                user_agent=(
                    "Mozilla/5.0 (Linux; Android 10; Pixel 3 XL) "
                    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
                ),
                viewport={"width": 412, "height": 915},
                is_mobile=True
            )
        self._ctx = self._browser.new_context(**context_kwargs)
        self._page = self._ctx.new_page()

    def open_home(self) -> None:
        self._page.goto("https://www.douyin.com/", wait_until="networkidle")
        self._page.wait_for_timeout(2500)

    def collect_video_links(self, seed_url: Optional[str], scrolls: int = 8) -> List[str]:
        if seed_url:
            self._page.goto(seed_url, wait_until="networkidle")
            self._page.wait_for_timeout(1500)

        results = set()
        for i in range(scrolls):
            hrefs = self._page.eval_on_selector_all(
                "a", "els => els.map(e => e.href).filter(Boolean)"
            )
            for h in hrefs:
                if is_video_link(h):
                    results.add(h)
            self._page.evaluate("window.scrollBy(0, window.innerHeight)")
            self._page.wait_for_timeout(1200 + (i % 3) * 300)
        return list(results)

    def shutdown(self) -> None:
        try:
            self._ctx.close()
            self._browser.close()
        finally:
            self._p.stop()
