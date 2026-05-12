from fastapi import FastAPI
from router import router

app = FastAPI(title="Python Services API")
app.include_router(router)
