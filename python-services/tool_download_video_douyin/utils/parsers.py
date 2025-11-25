from urllib.parse import urlparse

def normalize_url(url: str) -> str:
    # hook để chuẩn hóa link rút gọn, bỏ utm, v.v.
    return url.strip()
