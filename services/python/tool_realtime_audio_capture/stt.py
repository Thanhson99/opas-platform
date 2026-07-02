"""Optional faster-whisper adapter for realtime rolling audio transcription."""

import os
import re
import struct
import tempfile
import time
from collections import deque
from datetime import UTC, datetime
from importlib.util import find_spec
from pathlib import Path
from threading import Lock, Thread
from typing import Any

from .models import TranscriptLine

MAX_TRANSCRIPT_LINES = 10
MIN_TRANSCRIPT_CHARS = int(os.environ.get("OPAS_REALTIME_STT_MIN_CHARS", "3"))
MAX_OVERLAP_TOKENS = int(os.environ.get("OPAS_REALTIME_STT_MAX_OVERLAP_TOKENS", "40"))
KEEP_AUDIO_BUFFER = os.environ.get("OPAS_REALTIME_STT_KEEP_AUDIO_BUFFER", "0") == "1"
AUTO_WARMUP_MODEL = os.environ.get("OPAS_REALTIME_STT_AUTO_WARMUP", "1") != "0"
NORMALIZE_AUDIO = os.environ.get("OPAS_REALTIME_STT_NORMALIZE_AUDIO", "1") != "0"
NORMALIZE_TARGET_PEAK = float(os.environ.get("OPAS_REALTIME_STT_NORMALIZE_TARGET_PEAK", "0.82"))
NORMALIZE_MAX_GAIN = float(os.environ.get("OPAS_REALTIME_STT_NORMALIZE_MAX_GAIN", "6.0"))
LANGUAGE_CONFIDENCE_THRESHOLD = float(os.environ.get("OPAS_REALTIME_STT_LANGUAGE_CONFIDENCE_THRESHOLD", "0.35"))
MAX_PROMPT_CHARS = int(os.environ.get("OPAS_REALTIME_STT_MAX_PROMPT_CHARS", "600"))
NOISE_TRANSCRIPTS = {
    ".",
    "...",
    "thank you",
    "thanks for watching",
    "thank you for watching",
    "please subscribe",
    "subscribe",
    "bye",
    "you",
}
STT_PROFILES = {
    "live": {
        "model": "base",
        "interval_seconds": 0.75,
        "max_buffer_bytes": 128_000,
        "min_rms": 0.0005,
        "min_chunks": 3,
        "beam_size": 1,
    },
    "fast": {
        "model": "tiny",
        "interval_seconds": 0.5,
        "max_buffer_bytes": 96_000,
        "min_rms": 0.0005,
        "min_chunks": 2,
        "beam_size": 1,
    },
    "balanced": {
        "model": "base",
        "interval_seconds": 1.0,
        "max_buffer_bytes": 320_000,
        "min_rms": 0.0005,
        "min_chunks": 2,
        "beam_size": 3,
    },
    "accurate": {
        "model": "small",
        "interval_seconds": 1.5,
        "max_buffer_bytes": 480_000,
        "min_rms": 0.0005,
        "min_chunks": 3,
        "beam_size": 5,
    },
}

# Local macOS Python environments can load multiple OpenMP runtimes through
# faster-whisper/ctranslate2 dependencies. Without this, the process can abort
# during model initialization, which makes browser fetches look like network
# failures.
os.environ.setdefault("KMP_DUPLICATE_LIB_OK", "TRUE")


class OptionalWhisperStt:
    """Manage optional local faster-whisper transcription for capture sessions."""

    def __init__(self) -> None:
        """Initialize runtime state, profile configuration, and optional warmup."""
        self._lock = Lock()
        self._model: Any | None = None
        self._model_error: str | None = None
        self._model_loading = False
        self._session_formats: dict[str, str] = {}
        self._session_sample_rates: dict[str, int] = {}
        self._session_channels: dict[str, int] = {}
        self._session_files: dict[str, Path] = {}
        self._session_header_chunks: dict[str, bytes] = {}
        self._session_buffers: dict[str, deque[bytes]] = {}
        self._session_buffer_bytes: dict[str, int] = {}
        self._last_transcribe_at: dict[str, float] = {}
        self._last_text: dict[str, str] = {}
        self._lines: dict[str, list[TranscriptLine]] = {}
        self._transcribing_sessions: set[str] = set()
        self._pending_audio_cleanup_sessions: set[str] = set()
        self._pending_audio_cleanup_files: dict[str, Path] = {}
        self._session_rms: dict[str, float] = {}
        self._session_attempts: dict[str, int] = {}
        self._session_status: dict[str, str] = {}
        self._session_errors: dict[str, str | None] = {}
        requested_profile = os.environ.get("OPAS_REALTIME_STT_PROFILE", "balanced")
        self._profile = requested_profile if requested_profile in STT_PROFILES else "balanced"
        profile_config = STT_PROFILES[self._profile]
        self._model_name = os.environ.get("OPAS_REALTIME_STT_MODEL", profile_config["model"])
        self._interval_seconds = float(os.environ.get("OPAS_REALTIME_STT_INTERVAL_SECONDS", profile_config["interval_seconds"]))
        self._max_buffer_bytes = int(os.environ.get("OPAS_REALTIME_STT_MAX_BUFFER_BYTES", profile_config["max_buffer_bytes"]))
        self._min_rms = float(os.environ.get("OPAS_REALTIME_STT_MIN_RMS", profile_config["min_rms"]))
        self._min_chunks = int(os.environ.get("OPAS_REALTIME_STT_MIN_CHUNKS", profile_config["min_chunks"]))
        self._beam_size = int(os.environ.get("OPAS_REALTIME_STT_BEAM_SIZE", profile_config["beam_size"]))
        configured_languages = self._normalize_languages(os.environ.get("OPAS_REALTIME_STT_LANGUAGES"))
        configured_language = self._normalize_language(os.environ.get("OPAS_REALTIME_STT_LANGUAGE", "auto"))
        self._allowed_languages = configured_languages or ([configured_language] if configured_language else [])
        self._language = self._forced_language(self._allowed_languages)
        self._prompt_context = self._normalize_prompt(os.environ.get("OPAS_REALTIME_STT_PROMPT", ""))
        if AUTO_WARMUP_MODEL:
            self.warmup_model()

    def start_session(self, session_id: str, audio_format: Any | None = None) -> None:
        """Initialize rolling audio and transcript state for one session."""
        session_dir = Path(tempfile.gettempdir()) / "opas-realtime-audio"
        session_dir.mkdir(parents=True, exist_ok=True)
        format_name = getattr(audio_format, "format", None) or "webm_opus"
        suffix = ".wav" if format_name == "pcm_s16le" else ".webm"
        self._session_files[session_id] = session_dir / f"{session_id}{suffix}"
        self._session_formats[session_id] = format_name
        self._session_sample_rates[session_id] = int(getattr(audio_format, "sample_rate", 16000) or 16000)
        self._session_channels[session_id] = int(getattr(audio_format, "channels", 1) or 1)
        self._last_transcribe_at[session_id] = 0.0
        self._last_text[session_id] = ""
        self._lines[session_id] = []
        self._session_header_chunks[session_id] = b""
        self._session_buffers[session_id] = deque()
        self._session_buffer_bytes[session_id] = 0
        self._session_rms[session_id] = 0.0
        self._session_attempts[session_id] = 0
        self._session_status[session_id] = "waiting_for_audio"
        self._session_errors[session_id] = None
        self._session_files[session_id].write_bytes(b"")

    def stop_session(self, session_id: str) -> None:
        """Release session state while preserving files needed by active workers."""
        if not KEEP_AUDIO_BUFFER:
            with self._lock:
                is_transcribing = session_id in self._transcribing_sessions

            if is_transcribing:
                self._pending_audio_cleanup_sessions.add(session_id)
                audio_file = self._session_files.get(session_id)
                if audio_file is not None:
                    self._pending_audio_cleanup_files[session_id] = audio_file
            else:
                self._delete_session_audio_files(session_id)

        self._last_transcribe_at.pop(session_id, None)
        self._session_buffers.pop(session_id, None)
        self._session_buffer_bytes.pop(session_id, None)
        self._last_text.pop(session_id, None)
        self._session_header_chunks.pop(session_id, None)
        self._session_formats.pop(session_id, None)
        self._session_sample_rates.pop(session_id, None)
        self._session_channels.pop(session_id, None)
        self._transcribing_sessions.discard(session_id)
        self._session_rms.pop(session_id, None)
        self._session_attempts.pop(session_id, None)
        self._session_status.pop(session_id, None)
        self._session_errors.pop(session_id, None)

    def ingest_chunk(self, session_id: str, chunk: bytes, chunks_received: int) -> None:
        """Append one audio chunk and schedule transcription when thresholds pass."""
        audio_file = self._session_files.get(session_id)
        if audio_file is None:
            return

        self._append_rolling_chunk(session_id, chunk)
        audio_file.write_bytes(self._audio_file_bytes(session_id))
        self._session_status[session_id] = "buffering_audio"

        if chunks_received < self._min_chunks:
            return

        if self.rms_level(session_id) < self._min_rms:
            self._session_status[session_id] = "audio_below_rms_threshold"
            return

        now = time.monotonic()
        if now - self._last_transcribe_at.get(session_id, 0.0) < self._interval_seconds:
            return

        self._last_transcribe_at[session_id] = now
        self._transcribe_session_later(session_id, audio_file)

    def transcript_lines(self, session_id: str) -> list[TranscriptLine]:
        """Return the latest transcript lines for one session."""
        return self._lines.get(session_id, [])[-MAX_TRANSCRIPT_LINES:]

    def buffered_bytes(self, session_id: str) -> int:
        """Return the current rolling audio buffer size."""
        return self._session_buffer_bytes.get(session_id, 0)

    def rms_level(self, session_id: str) -> float:
        """Return the latest normalized RMS level for one session."""
        return self._session_rms.get(session_id, 0.0)

    def attempts(self, session_id: str) -> int:
        """Return the number of transcription attempts for one session."""
        return self._session_attempts.get(session_id, 0)

    def session_status(self, session_id: str) -> str:
        """Return the current STT status for one session."""
        return self._session_status.get(session_id, "idle")

    def session_error(self, session_id: str) -> str | None:
        """Return the latest STT error for one session."""
        return self._session_errors.get(session_id)

    def allowed_languages(self) -> list[str]:
        """Return the configured accepted language filter."""
        return list(self._allowed_languages)

    def audio_file_path(self, session_id: str) -> Path | None:
        """Return a readable current audio buffer path when available."""
        audio_file = self._session_files.get(session_id)
        if not audio_file or not audio_file.exists() or not audio_file.stat().st_size:
            return None

        return audio_file

    def is_available(self) -> bool:
        """Return whether faster-whisper is importable."""
        return find_spec("faster_whisper") is not None

    def health_status(self) -> str:
        """Return a compact STT readiness summary."""
        if self.is_available():
            model_state = "loaded" if self._model is not None else ("loading" if self._model_loading else "lazy")
            return (
                f"faster-whisper installed; profile={self._profile}; model={self._model_name}; "
                f"language={self._language or 'auto'}; model_state={model_state}"
            )

        return "faster-whisper not installed"

    def status_notes(self) -> list[str]:
        """Return user-facing STT status notes for the current runtime state."""
        if self._model_error:
            return [
                self._model_error,
                "Capture is working, but realtime text needs a local STT engine.",
            ]

        if self._model_loading:
            return [
                "STT model is loading in the background.",
                "The first transcript can take longer while faster-whisper initializes the model.",
            ]

        if self._model is None:
            return [
                "Audio is captured. STT will start after at least 2 audio chunks.",
                f"STT waits for audio RMS >= {self._min_rms:.4f} to avoid transcribing silence.",
                f"Audio normalization is {'enabled' if NORMALIZE_AUDIO else 'disabled'}.",
                "If the tab has no spoken voice yet, no transcript line will be emitted.",
            ]

        return [
            "Local faster-whisper STT is enabled.",
            f"Audio normalization is {'enabled' if NORMALIZE_AUDIO else 'disabled'}.",
        ]

    def warmup_model(self) -> tuple[bool, str]:
        """Start asynchronous faster-whisper model loading."""
        if not self.is_available():
            return False, "faster-whisper is not installed."

        if self._model is not None:
            return True, "STT model is already loaded."

        if self._model_loading:
            return True, "STT model is already loading."

        Thread(target=self._load_model, daemon=True, name="opas-stt-warmup").start()
        return True, "STT model warmup started."

    def config(self) -> dict[str, object]:
        """Return the active STT runtime configuration."""
        return {
            "profile": self._profile,
            "model": self._model_name,
            "interval_seconds": self._interval_seconds,
            "max_buffer_bytes": self._max_buffer_bytes,
            "min_rms": self._min_rms,
            "min_chunks": self._min_chunks,
            "beam_size": self._beam_size,
            "language": self._language or "auto",
            "languages": self._allowed_languages,
            "prompt": self._prompt_context,
            "normalize_audio": NORMALIZE_AUDIO,
            "language_confidence_threshold": LANGUAGE_CONFIDENCE_THRESHOLD,
            "auto_warmup": AUTO_WARMUP_MODEL,
            "model_loaded": self._model is not None,
            "model_loading": self._model_loading,
        }

    def configure_profile(
        self,
        profile: str,
        language: str | None = None,
        languages: list[str] | None = None,
        prompt: str | None = None,
    ) -> tuple[dict[str, object], str]:
        """Update profile and filtering without restarting the service."""
        if profile not in STT_PROFILES:
            profile = "live"

        profile_config = STT_PROFILES[profile]
        old_model_name = self._model_name
        old_languages = list(self._allowed_languages)
        old_prompt = self._prompt_context
        self._profile = profile
        self._model_name = str(profile_config["model"])
        self._interval_seconds = float(profile_config["interval_seconds"])
        self._max_buffer_bytes = int(profile_config["max_buffer_bytes"])
        self._min_rms = float(profile_config["min_rms"])
        self._min_chunks = int(profile_config["min_chunks"])
        self._beam_size = int(profile_config["beam_size"])
        if languages is not None:
            self._allowed_languages = self._normalize_languages(languages)
        elif language is not None:
            normalized_language = self._normalize_language(language)
            self._allowed_languages = [normalized_language] if normalized_language else []
        if prompt is not None:
            self._prompt_context = self._normalize_prompt(prompt)
        self._language = self._forced_language(self._allowed_languages)

        message = f"STT profile set to {profile}."
        if self._model is not None and self._model_name != old_model_name:
            self._model = None
            self._model_error = None
            message = f"STT profile set to {profile}. Model will reload as {self._model_name}."
            if AUTO_WARMUP_MODEL:
                self.warmup_model()
        elif old_languages != self._allowed_languages:
            language_label = ", ".join(self._allowed_languages) if self._allowed_languages else "auto/all"
            message = f"STT profile set to {profile}. Accepted languages: {language_label}."
        elif old_prompt != self._prompt_context:
            message = f"STT profile set to {profile}. Prompt context updated."

        return self.config(), message

    def force_transcribe(self, session_id: str) -> tuple[bool, str]:
        """Schedule an immediate transcription attempt for one session."""
        audio_file = self._session_files.get(session_id)
        if not audio_file or not audio_file.exists() or audio_file.stat().st_size <= 44:
            self._session_status[session_id] = "no_audio_buffer"
            return False, "No audio buffer is available yet."

        if session_id in self._transcribing_sessions:
            return True, "STT is already transcribing this session."

        self._transcribe_session_later(session_id, audio_file)
        return True, "STT force transcribe started."

    def _transcribe_session_later(self, session_id: str, audio_file: Path) -> None:
        """Create an immutable audio snapshot and transcribe it in a worker."""
        with self._lock:
            if session_id in self._transcribing_sessions:
                self._session_status[session_id] = "transcribe_already_running"
                return

            self._transcribing_sessions.add(session_id)
            self._session_attempts[session_id] = self._session_attempts.get(session_id, 0) + 1
            self._session_status[session_id] = "transcribing"
            self._session_errors[session_id] = None

        snapshot_file = audio_file.with_suffix(f".snapshot{audio_file.suffix}")
        snapshot_file.write_bytes(audio_file.read_bytes())
        Thread(
            target=self._transcribe_session,
            args=(session_id, snapshot_file),
            daemon=True,
            name=f"opas-stt-{session_id}",
        ).start()

    def _transcribe_session(self, session_id: str, audio_file: Path) -> None:
        """Run faster-whisper for a session snapshot and publish new text."""
        try:
            model = self._load_model()
            if model is None:
                self._session_status[session_id] = "model_unavailable"
                self._session_errors[session_id] = self._model_error or "STT model is not available."
                return

            transcribe_options = {
                "beam_size": self._beam_size,
                "vad_filter": True,
                "vad_parameters": {
                    "min_silence_duration_ms": 450,
                    "speech_pad_ms": 250,
                },
                "condition_on_previous_text": False,
                "temperature": 0.0,
                "compression_ratio_threshold": 2.4,
                "log_prob_threshold": -1.0,
                "no_speech_threshold": 0.6,
            }
            prompt = self._initial_prompt(self._language)
            if self._language:
                transcribe_options["language"] = self._language
            if prompt:
                transcribe_options["initial_prompt"] = prompt

            segments, info = model.transcribe(str(audio_file), **transcribe_options)
            text = self._clean_transcript_text(" ".join(segment.text.strip() for segment in segments if segment.text.strip()))
            detected_language = getattr(info, "language", None)
            confidence = getattr(info, "language_probability", None)
            self._model_error = None
            self._session_errors[session_id] = None
        except Exception as exc:  # pragma: no cover - depends on local codec/model setup.
            self._model_error = f"STT transcribe failed: {exc}"
            self._session_status[session_id] = "transcribe_failed"
            self._session_errors[session_id] = str(exc)
            return
        finally:
            with self._lock:
                self._transcribing_sessions.discard(session_id)
                should_cleanup = session_id in self._pending_audio_cleanup_sessions
                if should_cleanup:
                    self._pending_audio_cleanup_sessions.discard(session_id)

            if not KEEP_AUDIO_BUFFER:
                audio_file.unlink(missing_ok=True)
                if should_cleanup:
                    self._delete_session_audio_files(session_id, self._pending_audio_cleanup_files.pop(session_id, None))

        if self._should_drop_transcript(text):
            self._session_status[session_id] = "transcript_filtered"
            return

        normalized_detected_language = self._normalize_language(detected_language)
        language_filter_status = self._language_filter_status(normalized_detected_language, confidence)
        if language_filter_status:
            self._session_status[session_id] = language_filter_status
            return

        if self._normalize_for_compare(text) == self._normalize_for_compare(self._last_text.get(session_id, "")):
            self._session_status[session_id] = "duplicate_transcript"
            return

        previous_text = self._last_text.get(session_id, "")
        self._last_text[session_id] = text
        display_text = self._clean_transcript_text(self._extract_new_text(previous_text, text))
        if self._should_drop_transcript(display_text) or self._is_duplicate_line(session_id, display_text):
            self._session_status[session_id] = "duplicate_transcript"
            return

        with self._lock:
            self._session_status[session_id] = "transcript_ready"
            self._lines[session_id] = [
                *self._lines.get(session_id, []),
                TranscriptLine(
                    speaker="Speaker",
                    original_text=display_text,
                    detected_language=normalized_detected_language,
                    started_at=datetime.now(UTC).isoformat(),
                    confidence=confidence,
                ),
            ][-MAX_TRANSCRIPT_LINES:]

    def _append_rolling_chunk(self, session_id: str, chunk: bytes) -> None:
        """Append non-PCM chunks while keeping the rolling buffer bounded."""
        if self._session_formats.get(session_id) == "pcm_s16le":
            self._append_rolling_pcm_chunk(session_id, chunk)
            return

        if not self._session_header_chunks.get(session_id):
            self._session_header_chunks[session_id] = chunk
            self._session_buffer_bytes[session_id] = len(chunk)
            return

        buffer = self._session_buffers.setdefault(session_id, deque())
        buffer.append(chunk)
        self._session_buffer_bytes[session_id] = self._session_buffer_bytes.get(session_id, 0) + len(chunk)

        while self._session_buffer_bytes[session_id] > self._max_buffer_bytes and len(buffer) > 1:
            removed = buffer.popleft()
            self._session_buffer_bytes[session_id] -= len(removed)

    def _append_rolling_pcm_chunk(self, session_id: str, chunk: bytes) -> None:
        """Append PCM chunks and update the latest RMS level."""
        self._session_rms[session_id] = self._pcm_rms(chunk)
        buffer = self._session_buffers.setdefault(session_id, deque())
        buffer.append(chunk)
        self._session_buffer_bytes[session_id] = self._session_buffer_bytes.get(session_id, 0) + len(chunk)

        while self._session_buffer_bytes[session_id] > self._max_buffer_bytes and len(buffer) > 1:
            removed = buffer.popleft()
            self._session_buffer_bytes[session_id] -= len(removed)

    def _pcm_rms(self, chunk: bytes) -> float:
        """Calculate normalized RMS for signed 16-bit PCM audio."""
        sample_count = len(chunk) // 2
        if sample_count <= 0:
            return 0.0

        total = 0.0
        for (sample,) in struct.iter_unpack("<h", chunk[: sample_count * 2]):
            normalized = sample / 32768.0
            total += normalized * normalized

        return min((total / sample_count) ** 0.5, 1.0)

    def _audio_file_bytes(self, session_id: str) -> bytes:
        """Build the current audio file bytes consumed by faster-whisper."""
        if self._session_formats.get(session_id) == "pcm_s16le":
            pcm = b"".join(self._session_buffers.get(session_id, []))
            if NORMALIZE_AUDIO:
                pcm = self._normalize_pcm16(pcm)
            return self._wav_header(session_id, len(pcm)) + pcm

        return self._session_header_chunks.get(session_id, b"") + b"".join(self._session_buffers.get(session_id, []))

    def _wav_header(self, session_id: str, data_size: int) -> bytes:
        """Build a PCM WAV header for the rolling buffer."""
        channels = self._session_channels.get(session_id, 1)
        sample_rate = self._session_sample_rates.get(session_id, 16000)
        bits_per_sample = 16
        byte_rate = sample_rate * channels * bits_per_sample // 8
        block_align = channels * bits_per_sample // 8

        return struct.pack(
            "<4sI4s4sIHHIIHH4sI",
            b"RIFF",
            36 + data_size,
            b"WAVE",
            b"fmt ",
            16,
            1,
            channels,
            sample_rate,
            byte_rate,
            block_align,
            bits_per_sample,
            b"data",
            data_size,
        )

    def _extract_new_text(self, previous_text: str, text: str) -> str:
        """Return only the new suffix from an overlapping transcript window."""
        previous_text = self._clean_transcript_text(previous_text)
        text = self._clean_transcript_text(text)
        if not previous_text:
            return text

        if text.casefold().startswith(previous_text.casefold()):
            return text[len(previous_text) :].strip()

        previous_tokens = previous_text.split()
        text_tokens = text.split()
        previous_compare_tokens = [self._normalize_for_compare(token) for token in previous_tokens]
        text_compare_tokens = [self._normalize_for_compare(token) for token in text_tokens]
        max_overlap = min(len(previous_compare_tokens), len(text_compare_tokens), MAX_OVERLAP_TOKENS)

        for overlap_size in range(max_overlap, 0, -1):
            previous_slice = previous_compare_tokens[-overlap_size:]
            text_slice = text_compare_tokens[:overlap_size]
            if previous_slice == text_slice:
                return " ".join(text_tokens[overlap_size:]).strip()

        return text

    def _clean_transcript_text(self, text: str) -> str:
        """Normalize whitespace and punctuation in transcript text."""
        cleaned = re.sub(r"\s+", " ", text or "").strip()
        cleaned = re.sub(r"\s+([,.!?;:])", r"\1", cleaned)
        cleaned = re.sub(r"([,.!?;:]){3,}", r"\1", cleaned)
        return cleaned

    def _should_drop_transcript(self, text: str) -> bool:
        """Return whether a transcript is empty, too short, or noise."""
        cleaned = self._clean_transcript_text(text)
        if not cleaned:
            return True

        comparable = self._normalize_for_compare(cleaned)
        if len(comparable) < MIN_TRANSCRIPT_CHARS:
            return True

        return comparable in NOISE_TRANSCRIPTS

    def _is_duplicate_line(self, session_id: str, text: str) -> bool:
        """Return whether text duplicates the last emitted line."""
        lines = self._lines.get(session_id, [])
        if not lines:
            return False

        comparable = self._normalize_for_compare(text)
        return comparable == self._normalize_for_compare(lines[-1].original_text)

    def _normalize_for_compare(self, text: str) -> str:
        """Normalize text for duplicate and overlap comparison."""
        normalized = re.sub(r"[^\w\s]", "", text or "", flags=re.UNICODE)
        return re.sub(r"\s+", " ", normalized).strip().casefold()

    def _normalize_language(self, language: str | None) -> str | None:
        """Normalize language aliases into compact Whisper language codes."""
        if not language or language == "auto":
            return None

        aliases = {
            "zh-Hans": "zh",
            "zh-cn": "zh",
        }
        return aliases.get(language, language)

    def _normalize_languages(self, languages: list[str] | str | None) -> list[str]:
        """Normalize a language filter list or comma-separated string."""
        if not languages:
            return []

        values = languages.split(",") if isinstance(languages, str) else languages
        normalized = []
        for language in values:
            normalized_language = self._normalize_language(str(language).strip())
            if normalized_language and normalized_language not in normalized:
                normalized.append(normalized_language)

        return normalized

    def _normalize_prompt(self, prompt: str | None) -> str:
        """Normalize prompt context and enforce the configured length cap."""
        cleaned = re.sub(r"\s+", " ", prompt or "").strip()
        return cleaned[:MAX_PROMPT_CHARS]

    def _forced_language(self, languages: list[str]) -> str | None:
        """Force Whisper language only when exactly one language is allowed."""
        return languages[0] if len(languages) == 1 else None

    def _language_filter_status(self, detected_language: str | None, confidence: float | None) -> str | None:
        """Return a filter status when detected language should be ignored."""
        if not self._allowed_languages:
            return None

        if detected_language not in self._allowed_languages:
            return f"language_filtered:{detected_language or 'unknown'}"

        if confidence is not None and confidence < LANGUAGE_CONFIDENCE_THRESHOLD:
            return f"language_uncertain:{detected_language}:{confidence:.2f}"

        return None

    def _normalize_pcm16(self, pcm: bytes) -> bytes:
        """Peak-normalize signed 16-bit PCM without clipping."""
        sample_count = len(pcm) // 2
        if sample_count <= 0:
            return pcm

        samples = list(struct.iter_unpack("<h", pcm[: sample_count * 2]))
        peak = max(abs(sample[0]) for sample in samples)
        if peak <= 0:
            return pcm

        current_peak = peak / 32768.0
        gain = min(max(NORMALIZE_TARGET_PEAK / current_peak, 1.0), NORMALIZE_MAX_GAIN)
        if gain <= 1.01:
            return pcm

        normalized = bytearray(sample_count * 2)
        view = memoryview(normalized)
        for index, (sample,) in enumerate(samples):
            amplified = max(-32768, min(32767, round(sample * gain)))
            struct.pack_into("<h", view, index * 2, amplified)

        return bytes(normalized)

    def _initial_prompt(self, language: str | None) -> str | None:
        """Build the Whisper prompt from language and operator context."""
        prompts = {
            "vi": "Đây là lời thoại tiếng Việt. Hãy chép lại chính xác, có dấu tiếng Việt và dấu câu tự nhiên.",
            "en": "This is spoken English. Transcribe accurately with natural punctuation.",
            "ja": "これは日本語の会話です。正確に句読点付きで文字起こししてください。",
            "zh": "这是中文语音。请准确转写并加入自然标点。",
        }
        parts = []
        if language:
            language_prompt = prompts.get(language)
            if language_prompt:
                parts.append(language_prompt)
        elif self._allowed_languages:
            parts.append(
                f"Only transcribe speech in these languages: {', '.join(self._allowed_languages)}. "
                "Ignore other languages."
            )
        else:
            parts.append("Transcribe accurately with natural punctuation.")

        if self._prompt_context:
            parts.append(f"Important vocabulary and context: {self._prompt_context}")

        return " ".join(parts) if parts else None

    def _delete_session_audio_files(self, session_id: str, audio_file: Path | None = None) -> None:
        """Delete rolling audio files and any pending snapshot files."""
        audio_file = audio_file or self._session_files.get(session_id)
        if audio_file is None:
            return

        audio_file.unlink(missing_ok=True)
        for snapshot_file in audio_file.parent.glob(f"{audio_file.stem}.snapshot*"):
            snapshot_file.unlink(missing_ok=True)

    def _load_model(self) -> Any | None:
        """Load or wait for the configured faster-whisper model."""
        if self._model is not None:
            return self._model

        if self._model_error:
            return None

        if self._model_loading:
            for _ in range(600):
                time.sleep(0.1)
                if self._model is not None or self._model_error:
                    return self._model

            self._model_error = "Timed out while waiting for faster-whisper model to load."
            return None

        try:
            from faster_whisper import WhisperModel
        except ImportError:
            self._model_error = (
                "Python package faster-whisper is not installed. "
                "Install it and restart the realtime audio service to enable tab-audio transcript."
            )
            return None

        device = os.environ.get("OPAS_REALTIME_STT_DEVICE", "cpu")
        compute_type = os.environ.get("OPAS_REALTIME_STT_COMPUTE_TYPE", "int8")

        try:
            self._model_loading = True
            for session_id in self._transcribing_sessions:
                self._session_status[session_id] = "loading_model"
            self._model = WhisperModel(self._model_name, device=device, compute_type=compute_type)
        except Exception as exc:  # pragma: no cover - depends on local model cache/network.
            self._model_error = f"Could not load faster-whisper model '{self._model_name}': {exc}"
            return None
        finally:
            self._model_loading = False

        return self._model
