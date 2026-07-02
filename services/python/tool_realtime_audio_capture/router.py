"""FastAPI routes for capture, transcript, and STT control."""

import asyncio
import json

from fastapi import APIRouter, Request, WebSocket, WebSocketDisconnect
from fastapi.responses import FileResponse, HTMLResponse, StreamingResponse

from .models import (
    AudioChunkIngestResponse,
    AudioSourceListResponse,
    BrowserTabListResponse,
    CaptureHealthResponse,
    CaptureSessionListResponse,
    CaptureSessionResponse,
    CaptureSessionStartRequest,
    SessionDebugResponse,
    SttActionResponse,
    SttConfigRequest,
    SttConfigResponse,
    TranscriptResponse,
)
from .service import capture_service
from .ui import render_capture_ui

router = APIRouter()


@router.get("/ui", response_class=HTMLResponse)
def ui() -> HTMLResponse:
    """Return the standalone fallback capture UI."""
    return HTMLResponse(render_capture_ui())


@router.get("/health", response_model=CaptureHealthResponse)
def health() -> CaptureHealthResponse:
    """Return service and STT readiness."""
    return capture_service.get_health()


@router.get("/browser-tabs", response_model=BrowserTabListResponse)
def browser_tabs() -> BrowserTabListResponse:
    """Return browser tabs discovered without extension context."""
    return capture_service.list_browser_tabs()


@router.get("/sources", response_model=AudioSourceListResponse)
def sources() -> AudioSourceListResponse:
    """Return all known capture-source options."""
    return capture_service.list_sources()


@router.post("/sessions", response_model=CaptureSessionResponse)
def start_session(request: CaptureSessionStartRequest) -> CaptureSessionResponse:
    """Create a capture session for the selected source."""
    return capture_service.start_session(request)


@router.get("/sessions", response_model=CaptureSessionListResponse)
def list_sessions() -> CaptureSessionListResponse:
    """Return current capture sessions."""
    return capture_service.list_sessions()


@router.get("/sessions/{session_id}", response_model=CaptureSessionResponse)
def get_session(session_id: str) -> CaptureSessionResponse:
    """Return one capture session by id."""
    return capture_service.get_session(session_id)


@router.get("/sessions/{session_id}/transcript", response_model=TranscriptResponse)
def get_transcript(
    session_id: str,
    source_language: str | None = None,
    target_language: str | None = None,
) -> TranscriptResponse:
    """Return the rolling transcript for one session."""
    return capture_service.get_transcript(session_id, source_language, target_language)


@router.get("/sessions/{session_id}/events")
async def session_events(
    session_id: str,
    source_language: str | None = None,
    target_language: str | None = None,
) -> StreamingResponse:
    """Stream session and transcript updates as server-sent events."""
    async def event_stream():
        """Yield changed session payloads until the session stops."""
        last_payload = ""
        while True:
            session, transcript = await asyncio.to_thread(
                _session_event_payload,
                session_id,
                source_language,
                target_language,
            )
            payload = json.dumps(
                {
                    "session": session.model_dump(mode="json"),
                    "transcript": transcript.model_dump(mode="json"),
                }
            )
            if payload != last_payload:
                yield f"event: update\ndata: {payload}\n\n"
                last_payload = payload
            else:
                yield ": heartbeat\n\n"

            if session.status == "stopped":
                break

            await asyncio.sleep(0.6)

    return StreamingResponse(event_stream(), media_type="text/event-stream")


@router.websocket("/sessions/{session_id}/transcript/ws")
async def transcript_websocket(websocket: WebSocket, session_id: str) -> None:
    """Stream transcript updates through a websocket."""
    await websocket.accept()
    last_payload = ""
    try:
        while True:
            session, transcript = await asyncio.to_thread(
                _session_event_payload,
                session_id,
                None,
                None,
            )
            payload = json.dumps(transcript.model_dump(mode="json"))
            if payload != last_payload:
                await websocket.send_text(payload)
                last_payload = payload

            if session.status == "stopped":
                break

            await asyncio.sleep(0.45)
    except WebSocketDisconnect:
        return


def _session_event_payload(
    session_id: str,
    source_language: str | None,
    target_language: str | None,
) -> tuple[CaptureSessionResponse, TranscriptResponse]:
    """Build the shared session/transcript payload for live streams."""
    return (
        capture_service.get_session(session_id),
        capture_service.get_transcript(session_id, source_language, target_language),
    )


@router.get("/sessions/{session_id}/debug", response_model=SessionDebugResponse)
def get_session_debug(session_id: str) -> SessionDebugResponse:
    """Return capture and STT debug state for one session."""
    return capture_service.get_session_debug(session_id)


@router.post("/stt/warmup", response_model=SttActionResponse)
def warmup_stt() -> SttActionResponse:
    """Start loading the local STT model in the background."""
    return capture_service.warmup_stt()


@router.get("/stt/config", response_model=SttConfigResponse)
def stt_config() -> SttConfigResponse:
    """Return the active STT runtime configuration."""
    return capture_service.get_stt_config()


@router.post("/stt/config", response_model=SttConfigResponse)
def configure_stt(request: SttConfigRequest) -> SttConfigResponse:
    """Update STT profile, language filter, and prompt context."""
    return capture_service.configure_stt_profile(
        request.profile,
        request.language,
        request.languages,
        request.prompt,
    )


@router.post("/sessions/{session_id}/transcribe-now", response_model=SttActionResponse)
def transcribe_now(session_id: str) -> SttActionResponse:
    """Force a transcription attempt for the current audio buffer."""
    return capture_service.transcribe_now(session_id)


@router.get("/sessions/{session_id}/audio-buffer")
def get_session_audio_buffer(session_id: str) -> FileResponse:
    """Download the current rolling audio buffer for debugging."""
    audio_file = capture_service.get_session_audio_buffer_path(session_id)
    return FileResponse(
        audio_file,
        media_type="audio/wav" if audio_file.suffix == ".wav" else "audio/webm",
        filename=f"{session_id}{audio_file.suffix}",
    )


@router.post("/sessions/{session_id}/audio-chunk", response_model=AudioChunkIngestResponse)
async def ingest_audio_chunk(session_id: str, request: Request) -> AudioChunkIngestResponse:
    """Accept one raw audio chunk from the browser extension."""
    return capture_service.ingest_audio_chunk(session_id, await request.body())


@router.post("/sessions/{session_id}/stop", response_model=CaptureSessionResponse)
def stop_session(session_id: str) -> CaptureSessionResponse:
    """Stop one capture session and release its audio buffer."""
    return capture_service.stop_session(session_id)
