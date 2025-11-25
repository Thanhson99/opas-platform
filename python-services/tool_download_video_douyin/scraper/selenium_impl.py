from typing import List, Optional
import undetected_chromedriver as uc
from selenium.webdriver.common.by import By
from ..utils.selectors import is_video_link

class SeleniumScraper:
    def __init__(self):
        opts = uc.ChromeOptions()
        opts.add_argument(
            "--user-agent=Mozilla/5.0 (Linux; Android 10; Pixel 3 XL) "
            "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
        )
        self._driver = uc.Chrome(options=opts)
        self._driver.set_window_size(412, 915)

    def open_home(self) -> None:
        self._driver.get("https://www.douyin.com/")

    def collect_video_links(self, seed_url: Optional[str], scrolls: int = 8) -> List[str]:
        if seed_url:
            self._driver.get(seed_url)
        links = set()
        for i in range(scrolls):
            anchors = self._driver.find_elements(By.TAG_NAME, "a")
            for a in anchors:
                href = a.get_attribute("href")
                if href and is_video_link(href):
                    links.add(href)
            self._driver.execute_script("window.scrollBy(0, window.innerHeight);")
        return list(links)

    def shutdown(self) -> None:
        self._driver.quit()
