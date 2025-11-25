def is_video_link(h: str) -> bool:
    h = (h or "").lower()
    return (
        "/video/" in h
        or "douyin.com/video" in h
        or "share" in h
    )
