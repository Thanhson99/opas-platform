from fastapi import FastAPI

from .router import router

app = FastAPI(title="China Proxy Gateway")
app.include_router(router)
