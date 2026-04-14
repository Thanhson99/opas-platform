# China Proxy Gateway

This Python service does not create a China IP on its own.
It uses a real upstream proxy configured via environment variables and forwards requests only to approved Douyin domains.

## Environment

```env
CHINA_PROXY_URL=http://username:password@your-china-proxy-host:port
CHINA_PROXY_TIMEOUT=30
CHINA_PROXY_VERIFY_SSL=true
CHINA_PROXY_ALLOWED_HOSTS=douyin.com,www.douyin.com,v.douyin.com,iesdouyin.com,www.iesdouyin.com
```

## Endpoints

- `GET /china-proxy/hello`
- `GET /china-proxy/health`
- `POST /china-proxy/fetch`

## Sample Request

```json
{
  "target_url": "https://www.douyin.com/",
  "method": "GET",
  "headers": {
    "Accept": "text/html,application/xhtml+xml"
  },
  "query": {},
  "timeout": 20,
  "max_body_chars": 2000
}
```

## Notes

- Only `GET` and `HEAD` are supported in the initial version.
- By default, the service only allows hosts in the Douyin domain group.
- For actual video downloads, the Douyin service should use the same `CHINA_PROXY_URL` for both scraper and downloader traffic.
