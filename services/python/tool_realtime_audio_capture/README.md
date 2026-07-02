# Realtime Audio Capture

Local FastAPI service for Chromium tab-audio speech-to-text.

The active product flow is web-first with a small extension bridge:

1. The user clicks the extension popup `Open Web`.
2. The web screen at `/realtime-audio/ui` lists tabs through the extension bridge.
3. The user chooses a tab and clicks `Connect`.
4. The extension captures tab audio with `chrome.tabCapture`.
4. The offscreen document streams bounded PCM chunks to this service.
5. `faster-whisper` transcribes a rolling recent audio window.
6. The web screen receives transcript updates over WebSocket and renders only the latest few lines.

## Run

```bash
scripts/start-realtime-audio-capture.sh
```

Capture-only startup without STT dependency install:

```bash
scripts/start-realtime-audio-capture.sh --capture-only
```

Standalone local app:

```bash
cd services/python
python3 -m pip install -r tool_realtime_audio_capture/requirements.txt
python3 -m pip install -r tool_realtime_audio_capture/requirements-stt.txt
python3 -m uvicorn tool_realtime_audio_capture.main:app --host 127.0.0.1 --port 5010
```

Open the web screen:

```text
http://127.0.0.1:5010/realtime-audio/ui
```

The first STT run may download/load the configured Whisper model. Defaults are `OPAS_REALTIME_STT_PROFILE=live`, `OPAS_REALTIME_STT_DEVICE=cpu`, and `OPAS_REALTIME_STT_COMPUTE_TYPE=int8`.

STT profiles:

- `live`: `base`, low latency.
- `fast`: `tiny`, shortest latency and lower quality.
- `balanced`: `base`, larger rolling buffer.
- `accurate`: `small`, better text when CPU can keep up.

Runtime buffers are bounded for long meetings. The STT adapter keeps only a rolling audio buffer for recent chunks and the transcript endpoint returns the latest 10 lines instead of growing indefinitely.

Temporary audio files are deleted when a session stops. Set `OPAS_REALTIME_STT_KEEP_AUDIO_BUFFER=1` only when you need to debug `/audio-buffer` after stopping a session.

The Chromium extension streams tab audio as 16 kHz mono `pcm_s16le`. The backend wraps the rolling PCM buffer in a WAV header before sending it to `faster-whisper`.

## API

- `GET /realtime-audio/health`
- `GET /realtime-audio/browser-tabs`
- `GET /realtime-audio/sources`
- `POST /realtime-audio/stt/warmup`
- `GET /realtime-audio/stt/config`
- `POST /realtime-audio/stt/config`
- `POST /realtime-audio/sessions`
- `GET /realtime-audio/sessions`
- `GET /realtime-audio/sessions/{session_id}`
- `GET /realtime-audio/sessions/{session_id}/transcript`
- `GET /realtime-audio/sessions/{session_id}/events`
- `WS /realtime-audio/sessions/{session_id}/transcript/ws`
- `GET /realtime-audio/sessions/{session_id}/debug`
- `GET /realtime-audio/sessions/{session_id}/audio-buffer`
- `POST /realtime-audio/sessions/{session_id}/transcribe-now`
- `POST /realtime-audio/sessions/{session_id}/audio-chunk`
- `POST /realtime-audio/sessions/{session_id}/stop`

Example start request:

```json
{
  "source_id": "extension-tab:123",
  "source_label": "Meeting tab",
  "source_url": "https://example.test",
  "source_browser": "Chromium Extension",
  "audio_format": {
    "format": "pcm_s16le",
    "sample_rate": 16000,
    "channels": 1,
    "chunk_ms": 250
  }
}
```
