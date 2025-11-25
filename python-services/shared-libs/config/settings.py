from pydantic import BaseSettings, Field

class Settings(BaseSettings):
    ENV: str = Field("dev", description="dev|staging|prod")
    LOG_LEVEL: str = "INFO"

    # Tool Douyin
    TOOL_DOUYIN_BACKEND: str = Field(
        "playwright", description="playwright|selenium"
    )
    # Playwright headless/headful
    PW_HEADLESS: bool = False
    PW_MOBILE_UA: bool = True

    class Config:
        env_file = ".env"
        case_sensitive = False

settings = Settings()
