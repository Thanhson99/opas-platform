from pydantic import BaseSettings, Field


class ProxyGatewaySettings(BaseSettings):
    china_proxy_url: str = Field("", env="CHINA_PROXY_URL")
    china_proxy_timeout: float = Field(30.0, env="CHINA_PROXY_TIMEOUT")
    china_proxy_verify_ssl: bool = Field(True, env="CHINA_PROXY_VERIFY_SSL")
    china_proxy_allowed_hosts: str = Field(
        "douyin.com,www.douyin.com,v.douyin.com,iesdouyin.com,www.iesdouyin.com",
        env="CHINA_PROXY_ALLOWED_HOSTS",
    )

    class Config:
        env_file = ".env"
        case_sensitive = False

    @property
    def allowed_hosts(self) -> set[str]:
        return {
            host.strip().lower()
            for host in self.china_proxy_allowed_hosts.split(",")
            if host.strip()
        }


settings = ProxyGatewaySettings()
