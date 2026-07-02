"""Application service for realtime audio capture sessions."""

import subprocess
from datetime import UTC, datetime
from platform import system
from pathlib import Path
from uuid import uuid4

from fastapi import HTTPException

from .models import (
    AudioFormat,
    AudioChunkIngestResponse,
    AudioSource,
    AudioSourceListResponse,
    AudioSourceType,
    BrowserTab,
    BrowserTabListResponse,
    CaptureHealthResponse,
    CaptureSessionListResponse,
    CaptureSessionResponse,
    CaptureSessionStartRequest,
    CaptureSessionStatus,
    SessionDebugResponse,
    SttActionResponse,
    SttConfigResponse,
    TranscriptResponse,
)
from .stt import OptionalWhisperStt


class RealtimeAudioCaptureService:
    """Coordinate capture sessions, source discovery, and STT state."""

    def __init__(self) -> None:
        """Initialize in-memory session storage and the STT adapter."""
        self._sessions: dict[str, CaptureSessionResponse] = {}
        self._session_audio_bytes: dict[str, int] = {}
        self._stt = OptionalWhisperStt()

    def get_health(self) -> CaptureHealthResponse:
        """Return service health and STT availability."""
        return CaptureHealthResponse(
            ok=True,
            platform=self._platform_name(),
            active_sessions=self._active_session_count(),
            supported_source_types=[
                AudioSourceType.BROWSER_TAB,
                AudioSourceType.SYSTEM_AUDIO,
                AudioSourceType.MICROPHONE,
                AudioSourceType.VIRTUAL_DEVICE,
            ],
            stt_available=self._stt.is_available(),
            stt_status=self._stt.health_status(),
        )

    def list_sources(self) -> AudioSourceListResponse:
        """Return currently supported and placeholder audio sources."""
        platform_name = self._platform_name()
        browser_tab_sources = [self._browser_tab_source(tab) for tab in self.list_browser_tabs().tabs]

        return AudioSourceListResponse(
            platform=platform_name,
            sources=[
                self._browser_tab_placeholder(platform_name),
                *browser_tab_sources,
                self._system_audio_source(platform_name),
                self._microphone_placeholder(platform_name),
            ],
            notes=self._platform_notes(platform_name),
        )

    def list_browser_tabs(self) -> BrowserTabListResponse:
        """Discover browser tabs where platform support exists."""
        platform_name = self._platform_name()
        if platform_name != "darwin":
            return BrowserTabListResponse(
                platform=platform_name,
                tabs=[],
                notes=["Browser tab discovery is currently implemented for macOS only."],
            )

        tabs, notes = self._discover_macos_browser_tabs()

        return BrowserTabListResponse(platform=platform_name, tabs=tabs, notes=notes)

    def start_session(self, request: CaptureSessionStartRequest) -> CaptureSessionResponse:
        """Start a capture session and initialize its STT buffer."""
        source = self._resolve_source(request)
        if not source.is_available:
            raise HTTPException(status_code=400, detail=f"Audio source '{request.source_id}' is not available.")

        session = CaptureSessionResponse(
            session_id=f"rt-{uuid4()}",
            source=source,
            audio_format=request.audio_format,
            status=CaptureSessionStatus.CAPTURING,
            started_at=self._now(),
            stream_url=request.stream_url,
        )

        self._sessions[session.session_id] = session
        self._session_audio_bytes[session.session_id] = 0
        self._stt.start_session(session.session_id, session.audio_format)
        return session

    def get_session(self, session_id: str) -> CaptureSessionResponse:
        """Return one session with refreshed STT metrics."""
        session = self._sessions.get(session_id)
        if session is None:
            raise HTTPException(status_code=404, detail=f"Capture session '{session_id}' was not found.")

        refreshed_session = self._refresh_session_stt_state(session)
        self._sessions[session_id] = refreshed_session
        return refreshed_session

    def list_sessions(self) -> CaptureSessionListResponse:
        """Return all known sessions with live STT state."""
        return CaptureSessionListResponse(sessions=[self.get_session(session_id) for session_id in self._sessions])

    def stop_session(self, session_id: str) -> CaptureSessionResponse:
        """Stop one session and ask STT to release audio state."""
        session = self.get_session(session_id)
        stopped_session = session.model_copy(
            update={
                "status": CaptureSessionStatus.STOPPED,
                "stopped_at": self._now(),
                "volume_level": 0.0,
            }
        )

        self._sessions[session_id] = stopped_session
        self._stt.stop_session(session_id)
        return stopped_session

    def ingest_audio_chunk(self, session_id: str, chunk: bytes) -> AudioChunkIngestResponse:
        """Accept one chunk and advance the rolling transcription buffer."""
        session = self.get_session(session_id)
        if session.status != CaptureSessionStatus.CAPTURING:
            raise HTTPException(status_code=400, detail=f"Capture session '{session_id}' is not capturing.")

        chunk_size = len(chunk)
        total_bytes = self._session_audio_bytes.get(session_id, 0) + chunk_size
        chunks_received = session.chunks_sent + 1
        self._stt.ingest_chunk(session_id, chunk, chunks_received)
        updated_session = session.model_copy(
            update={
                "chunks_sent": chunks_received,
                "volume_level": self._stt.rms_level(session_id),
                "total_bytes_received": total_bytes,
                "buffered_bytes_received": self._stt.buffered_bytes(session_id),
                "audio_rms_level": self._stt.rms_level(session_id),
                "stt_attempts": self._stt.attempts(session_id),
                "stt_status": self._stt.session_status(session_id),
                "stt_last_error": self._stt.session_error(session_id),
            }
        )

        self._sessions[session_id] = updated_session
        self._session_audio_bytes[session_id] = total_bytes

        return AudioChunkIngestResponse(
            session_id=session_id,
            accepted=True,
            chunk_bytes=chunk_size,
            chunks_received=chunks_received,
            total_bytes_received=total_bytes,
        )

    def get_transcript(
        self,
        session_id: str,
        source_language: str | None = None,
        target_language: str | None = None,
    ) -> TranscriptResponse:
        """Return the recent transcript lines and current STT notes."""
        session = self.get_session(session_id)
        lines = self._stt.transcript_lines(session_id)
        if lines:
            return TranscriptResponse(
                session_id=session_id,
                status="transcript_ready",
                lines=lines,
                notes=[
                    "Realtime tab-audio transcript is active.",
                    f"STT attempts: {self._stt.attempts(session_id)}.",
                ],
            )

        stt_notes = self._stt.status_notes()
        if session.chunks_sent > 0:
            return TranscriptResponse(
                session_id=session_id,
                status="audio_captured_waiting_for_stt",
                lines=[],
                notes=[
                    (
                        f"Audio capture is working: {session.chunks_sent} chunks, "
                        f"{session.buffered_bytes_received} buffered bytes."
                    ),
                    f"Audio RMS level: {self._stt.rms_level(session_id):.4f}.",
                    f"STT status: {self._stt.session_status(session_id)}.",
                    f"STT attempts: {self._stt.attempts(session_id)}.",
                    f"Accepted languages: {', '.join(self._stt.allowed_languages()) or 'all detected languages'}.",
                    *stt_notes,
                ],
            )

        return TranscriptResponse(
            session_id=session_id,
            status="waiting_for_stt",
            lines=[],
            notes=[
                "Connected to the selected source session, but no audio chunks have arrived yet.",
                f"Accepted languages: {', '.join(self._stt.allowed_languages()) or 'all detected languages'}.",
                *stt_notes,
            ],
        )

    def get_session_debug(self, session_id: str) -> SessionDebugResponse:
        """Return low-level capture counters for diagnostics."""
        session = self.get_session(session_id)

        return SessionDebugResponse(
            session_id=session.session_id,
            source_label=session.source.label,
            audio_format=session.audio_format,
            chunks_sent=session.chunks_sent,
            total_bytes_received=session.total_bytes_received,
            buffered_bytes_received=session.buffered_bytes_received,
            audio_rms_level=session.audio_rms_level,
            stt_available=self._stt.is_available(),
            stt_status=session.stt_status,
            stt_attempts=session.stt_attempts,
            stt_last_error=session.stt_last_error,
        )

    def get_session_audio_buffer_path(self, session_id: str) -> Path:
        """Return the current audio buffer path for a session."""
        self.get_session(session_id)
        audio_file = self._stt.audio_file_path(session_id)
        if audio_file is None:
            raise HTTPException(status_code=404, detail="No audio buffer is available for this session yet.")

        return audio_file

    def warmup_stt(self) -> SttActionResponse:
        """Start local STT model loading before capture begins."""
        accepted, message = self._stt.warmup_model()

        return SttActionResponse(
            accepted=accepted,
            status="warming" if accepted else "unavailable",
            message=message,
            stt_available=self._stt.is_available(),
        )

    def get_stt_config(self) -> SttConfigResponse:
        """Return active STT runtime configuration."""
        return SttConfigResponse(**self._stt.config(), message="STT config loaded.")

    def configure_stt_profile(
        self,
        profile: str,
        language: str | None = None,
        languages: list[str] | None = None,
        prompt: str | None = None,
    ) -> SttConfigResponse:
        """Update STT profile, language filter, and prompt context."""
        config, message = self._stt.configure_profile(profile, language, languages, prompt)

        return SttConfigResponse(**config, message=message)

    def transcribe_now(self, session_id: str) -> SttActionResponse:
        """Force a transcription attempt from the current audio buffer."""
        self.get_session(session_id)
        accepted, message = self._stt.force_transcribe(session_id)
        refreshed_session = self.get_session(session_id)

        return SttActionResponse(
            accepted=accepted,
            status=refreshed_session.stt_status,
            message=message,
            stt_available=self._stt.is_available(),
        )

    def _refresh_session_stt_state(self, session: CaptureSessionResponse) -> CaptureSessionResponse:
        """Copy live STT counters into a session response."""
        return session.model_copy(
            update={
                "buffered_bytes_received": self._stt.buffered_bytes(session.session_id),
                "volume_level": self._stt.rms_level(session.session_id),
                "audio_rms_level": self._stt.rms_level(session.session_id),
                "stt_attempts": self._stt.attempts(session.session_id),
                "stt_status": self._stt.session_status(session.session_id),
                "stt_last_error": self._stt.session_error(session.session_id),
            }
        )

    def _find_source(self, source_id: str) -> AudioSource:
        """Resolve a declared source by id."""
        for source in self.list_sources().sources:
            if source.id == source_id:
                return source

        raise HTTPException(status_code=404, detail=f"Audio source '{source_id}' was not found.")

    def _resolve_source(self, request: CaptureSessionStartRequest) -> AudioSource:
        """Resolve extension tabs separately from discovered sources."""
        if request.source_id.startswith("extension-tab:"):
            return self._extension_browser_tab_source(request)

        return self._find_source(request.source_id)

    def _extension_browser_tab_source(self, request: CaptureSessionStartRequest) -> AudioSource:
        """Build an available source from extension-provided tab metadata."""
        label = request.source_label or "Browser tab audio"

        return AudioSource(
            id=request.source_id,
            label=label,
            type=AudioSourceType.BROWSER_TAB,
            platform=self._platform_name(),
            adapter="chrome-extension-tab-capture",
            is_available=True,
            requires_setup=False,
            setup_hint=None,
            metadata={
                "browser": request.source_browser or "chromium",
                "url": request.source_url or "",
                "capture_status": "audio-chunks",
            },
        )

    def _active_session_count(self) -> int:
        """Count sessions that are still active."""
        active_statuses = {CaptureSessionStatus.STARTING, CaptureSessionStatus.CAPTURING}

        return sum(1 for session in self._sessions.values() if session.status in active_statuses)

    def _browser_tab_placeholder(self, platform_name: str) -> AudioSource:
        """Expose browser-tab capability before extension connection."""
        return AudioSource(
            id="browser-tab-placeholder",
            label="Browser tab audio",
            type=AudioSourceType.BROWSER_TAB,
            platform=platform_name,
            adapter="browser-extension-or-electron",
            is_available=False,
            requires_setup=True,
            setup_hint="Install a browser extension or desktop capture client to expose tab audio.",
            metadata={
                "supported_browsers": "chrome,coc-coc,brave,edge",
                "phase": "source-discovery",
            },
        )

    def _browser_tab_source(self, tab: BrowserTab) -> AudioSource:
        """Convert a discovered tab into a source option."""
        return AudioSource(
            id=tab.source_id,
            label=f"{tab.browser}: {tab.title}",
            type=AudioSourceType.BROWSER_TAB,
            platform=self._platform_name(),
            adapter="browser-tab-discovery-skeleton",
            is_available=True,
            requires_setup=True,
            setup_hint=tab.setup_hint,
            metadata={
                "browser": tab.browser,
                "window_index": str(tab.window_index),
                "tab_index": str(tab.tab_index),
                "url": tab.url,
                "capture_status": "skeleton",
            },
        )

    def _system_audio_source(self, platform_name: str) -> AudioSource:
        """Expose the platform-specific system-audio placeholder."""
        return AudioSource(
            id="system-audio-default",
            label="Default system output",
            type=AudioSourceType.SYSTEM_AUDIO,
            platform=platform_name,
            adapter=self._system_audio_adapter(platform_name),
            is_available=True,
            requires_setup=self._requires_system_audio_setup(platform_name),
            setup_hint=self._system_audio_setup_hint(platform_name),
            metadata={
                "phase": "source-discovery",
                "capture_status": "skeleton",
            },
        )

    def _microphone_placeholder(self, platform_name: str) -> AudioSource:
        """Expose a disabled microphone placeholder for future work."""
        return AudioSource(
            id="microphone-default",
            label="Default microphone",
            type=AudioSourceType.MICROPHONE,
            platform=platform_name,
            adapter="future-input-device-adapter",
            is_available=False,
            requires_setup=True,
            setup_hint="Microphone capture is outside OPAS-0101 phase 1 and will be enabled after adapter selection.",
            metadata={"phase": "source-discovery"},
        )

    def _platform_notes(self, platform_name: str) -> list[str]:
        """Return operator notes for platform-specific capture setup."""
        if platform_name == "darwin":
            return [
                "macOS system audio may require ScreenCaptureKit or a virtual audio device such as BlackHole.",
                "Browser tab audio should be implemented through a browser extension or Electron/Tauri client.",
            ]

        if platform_name == "windows":
            return [
                "Windows system audio should use WASAPI loopback in the next implementation step.",
                "Browser tab audio should still be verified separately for Chrome, Coc Coc, Brave, and Edge.",
            ]

        if platform_name == "linux":
            return [
                "Linux system audio should use PulseAudio or PipeWire monitor sources in the next implementation step.",
                "Browser tab audio should still be verified separately for Chromium-based browsers.",
            ]

        return ["Unknown platform. Source discovery is running with generic placeholders."]

    def _system_audio_adapter(self, platform_name: str) -> str:
        """Return the planned adapter name for system audio."""
        adapters = {
            "darwin": "screencapturekit-or-blackhole",
            "windows": "wasapi-loopback",
            "linux": "pulseaudio-or-pipewire-monitor",
        }

        return adapters.get(platform_name, "unknown-system-audio-adapter")

    def _requires_system_audio_setup(self, platform_name: str) -> bool:
        """Return whether system audio needs extra local setup."""
        return platform_name in {"darwin", "unknown"}

    def _system_audio_setup_hint(self, platform_name: str) -> str | None:
        """Return a platform-specific setup hint for system audio."""
        if platform_name == "darwin":
            return "Install/configure BlackHole for quick POC, or implement native ScreenCaptureKit capture."

        if platform_name == "unknown":
            return "Confirm OS-level loopback audio support before enabling capture."

        return None

    def _platform_name(self) -> str:
        """Normalize the current operating system name."""
        normalized_platforms = {
            "Darwin": "darwin",
            "Windows": "windows",
            "Linux": "linux",
        }

        return normalized_platforms.get(system(), "unknown")

    def _now(self) -> str:
        """Return a UTC timestamp for response payloads."""
        return datetime.now(UTC).isoformat()

    def _discover_macos_browser_tabs(self) -> tuple[list[BrowserTab], list[str]]:
        """Discover Chromium-family tabs through macOS Automation."""
        browsers = ["Google Chrome", "Brave Browser", "Microsoft Edge", "Cốc Cốc", "CocCoc"]
        running_browsers, running_browser_error = self._running_macos_browser_names(browsers)
        tabs: list[BrowserTab] = []
        notes: list[str] = []

        if running_browser_error:
            notes.append(running_browser_error)

        if running_browsers:
            notes.append(f"Detected running browsers: {', '.join(running_browsers)}.")

        for browser in browsers:
            if running_browsers and browser not in running_browsers:
                continue

            discovered_tabs, error = self._discover_macos_browser_tabs_for_app(browser)
            tabs.extend(discovered_tabs)
            if error:
                notes.append(error)

        if not tabs:
            notes.append(
                "No readable Chromium browser tabs found. Open Chrome/Brave/Edge/Coc Coc or allow Automation access."
            )

        notes.append("Tab audio capture still requires a browser extension or desktop capture adapter.")
        notes.append("Spoken language and transcript detection start in OPAS-0102 after audio capture is available.")

        return tabs, notes

    def _discover_macos_browser_tabs_for_app(self, browser: str) -> tuple[list[BrowserTab], str | None]:
        """Run AppleScript tab discovery for one macOS browser app."""
        script = f'''
        set output to ""
        tell application "{browser}"
            repeat with windowIndex from 1 to count of windows
                set tabIndex to 0
                repeat with browserTab in tabs of window windowIndex
                    set tabIndex to tabIndex + 1
                    set tabTitle to title of browserTab
                    set tabUrl to URL of browserTab
                    set output to output & windowIndex & "|||" & tabIndex & "|||" & tabTitle & "|||" & tabUrl & linefeed
                end repeat
            end repeat
        end tell

        return output
        '''

        try:
            result = subprocess.run(
                ["osascript", "-e", script],
                check=False,
                capture_output=True,
                text=True,
                timeout=5,
            )
        except (OSError, subprocess.SubprocessError) as exc:
            return [], f"{browser}: tab discovery failed: {exc}"

        if result.returncode != 0:
            error = result.stderr.strip() or "unknown AppleScript error"
            return [], f"{browser}: {error}"

        return self._parse_browser_tab_output(browser, result.stdout), None

    def _parse_browser_tab_output(self, browser: str, output: str) -> list[BrowserTab]:
        """Parse AppleScript tab output into browser-tab models."""
        tabs: list[BrowserTab] = []
        for line in output.splitlines():
            parts = line.split("|||", 3)
            if len(parts) != 4:
                continue

            window_index, tab_index, title, url = parts
            tabs.append(
                BrowserTab(
                    source_id=self._browser_tab_source_id(browser, window_index, tab_index),
                    browser=browser,
                    window_index=int(window_index),
                    tab_index=int(tab_index),
                    title=title or "Untitled tab",
                    url=url,
                    is_capture_ready=False,
                    setup_hint="Selection is ready. Real tab audio capture needs the browser extension/Electron adapter.",
                )
            )

        return tabs

    def _running_macos_browser_names(self, browsers: list[str]) -> tuple[list[str], str | None]:
        """Return the configured browser apps that are currently running."""
        script = '''
        tell application "System Events"
            return name of processes
        end tell
        '''

        try:
            result = subprocess.run(
                ["osascript", "-e", script],
                check=False,
                capture_output=True,
                text=True,
                timeout=3,
            )
        except (OSError, subprocess.SubprocessError):
            return [], "Could not inspect running browser apps through macOS Automation."

        if result.returncode != 0:
            return [], "macOS blocked running-app discovery. Allow Automation access for the terminal app."

        running_processes = {process_name.strip() for process_name in result.stdout.split(",") if process_name.strip()}

        return [browser for browser in browsers if browser in running_processes], None

    def _browser_tab_source_id(self, browser: str, window_index: str, tab_index: str) -> str:
        """Build a stable source id for a discovered browser tab."""
        normalized_browser = browser.lower().replace(" ", "-")

        return f"browser-tab:{normalized_browser}:{window_index}:{tab_index}"


capture_service = RealtimeAudioCaptureService()
