from pydantic import BaseModel, HttpUrl
from typing import List, Optional

class CrawlRequest(BaseModel):
    seed_url: Optional[HttpUrl] = None
    scrolls: int = 8

class CrawlResponse(BaseModel):
    count: int
    links: List[str]
    backend: str
