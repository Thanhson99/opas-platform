"""Pydantic contracts for the realtime audio capture API."""

from enum import Enum

from pydantic import BaseModel, Field


class AudioSourceType(str, Enum):
    """Supported capture-source categories exposed by the service."""

    BROWSER_TAB = "browser_tab"
    SYSTEM_AUDIO = "system_audio"
    MICROPHONE = "microphone"
    VIRTUAL_DEVICE = "virtual_device"
    UNKNOWN = "unknown"


class CaptureSessionStatus(str, Enum):
    """Lifecycle states for one audio capture session."""

    IDLE = "idle"
    STARTING = "starting"
    CAPTURING = "capturing"
    INTERRUPTED = "interrupted"
    STOPPING = "stopping"
    STOPPED = "stopped"
    FAILED = "failed"


class AudioFormat(BaseModel):
    """Audio format expected by the ingest endpoint."""

    format: str = "pcm_s16le"
    sample_rate: int = 16000
    channels: int = 1
    chunk_ms: int = 250


class AudioSource(BaseModel):
    """One selectable audio source."""

    id: str
    label: str
    type: AudioSourceType
    platform: str
    adapter: str
    is_available: bool = True
    requires_setup: bool = False
    setup_hint: str | None = None
    metadata: dict[str, str] = Field(default_factory=dict)


class AudioSourceListResponse(BaseModel):
    """Response containing available capture sources."""

    platform: str
    sources: list[AudioSource]
    notes: list[str] = Field(default_factory=list)


class BrowserTab(BaseModel):
    """Browser tab metadata used by extension and discovery flows."""

    source_id: str
    browser: str
    window_index: int
    tab_index: int
    title: str
    url: str
    is_capture_ready: bool = False
    setup_hint: str | None = None


class BrowserTabListResponse(BaseModel):
    """Response containing discoverable browser tabs."""

    platform: str
    tabs: list[BrowserTab]
    notes: list[str] = Field(default_factory=list)


class TranscriptLine(BaseModel):
    """One emitted transcript line in the rolling realtime window."""

    speaker: str | None = None
    original_text: str
    translated_text: str | None = None
    detected_language: str | None = None
    source_language: str | None = None
    target_language: str | None = None
    translation_status: str | None = None
    started_at: str | None = None
    ended_at: str | None = None
    confidence: float | None = None


class TranscriptResponse(BaseModel):
    """Rolling transcript response for one capture session."""

    session_id: str
    status: str
    lines: list[TranscriptLine] = Field(default_factory=list)
    notes: list[str] = Field(default_factory=list)


class CaptureSessionStartRequest(BaseModel):
    """Request payload for starting a capture session."""

    source_id: str
    audio_format: AudioFormat = Field(default_factory=AudioFormat)
    stream_url: str | None = None
    source_label: str | None = None
    source_url: str | None = None
    source_browser: str | None = None


class CaptureSessionResponse(BaseModel):
    """Current state of one capture session."""

    session_id: str
    source: AudioSource
    audio_format: AudioFormat
    status: CaptureSessionStatus
    started_at: str
    stopped_at: str | None = None
    stream_url: str | None = None
    volume_level: float = 0.0
    chunks_sent: int = 0
    dropped_chunks: int = 0
    last_error: str | None = None
    total_bytes_received: int = 0
    buffered_bytes_received: int = 0
    audio_rms_level: float = 0.0
    stt_attempts: int = 0
    stt_status: str = "idle"
    stt_last_error: str | None = None


class CaptureSessionListResponse(BaseModel):
    """Response containing active and stopped capture sessions."""

    sessions: list[CaptureSessionResponse]


class CaptureHealthResponse(BaseModel):
    """Health snapshot for the realtime audio service."""

    ok: bool
    platform: str
    active_sessions: int
    supported_source_types: list[AudioSourceType]
    stt_available: bool = False
    stt_status: str = "not_checked"


class AudioChunkIngestResponse(BaseModel):
    """Acknowledgement for an accepted audio chunk."""

    session_id: str
    accepted: bool
    chunk_bytes: int
    chunks_received: int
    total_bytes_received: int


class SessionDebugResponse(BaseModel):
    """Debug snapshot for capture and STT state."""

    session_id: str
    source_label: str
    audio_format: AudioFormat
    chunks_sent: int
    total_bytes_received: int
    buffered_bytes_received: int
    audio_rms_level: float
    stt_available: bool
    stt_status: str
    stt_attempts: int
    stt_last_error: str | None = None


class SttActionResponse(BaseModel):
    """Result of an STT control action."""

    accepted: bool
    status: str
    message: str
    stt_available: bool


class SttConfigRequest(BaseModel):
    """Runtime STT configuration update request."""

    profile: str = "balanced"
    language: str | None = None
    languages: list[str] | None = None
    prompt: str | None = None


class SttConfigResponse(BaseModel):
    """Runtime STT configuration snapshot."""

    profile: str
    model: str
    interval_seconds: float
    max_buffer_bytes: int
    min_rms: float
    min_chunks: int
    beam_size: int
    language: str
    languages: list[str]
    prompt: str
    normalize_audio: bool
    language_confidence_threshold: float
    auto_warmup: bool
    model_loaded: bool
    model_loading: bool
    message: str
