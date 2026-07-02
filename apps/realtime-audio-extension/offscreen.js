let mediaStream = null;
let audioContext = null;
let sourceNode = null;
let processorNode = null;
let silenceGainNode = null;
let activeSession = null;
let chunksSent = 0;
let bytesSent = 0;
let pcmParts = [];
let pcmBytes = 0;
let flushPromise = null;

const TARGET_SAMPLE_RATE = 16000;
const CHUNK_BYTES = TARGET_SAMPLE_RATE / 2;
const FETCH_TIMEOUT_MS = 3000;

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!['OFFSCREEN_START_CAPTURE', 'OFFSCREEN_STOP_CAPTURE'].includes(message?.action)) {
    return false;
  }

  handleMessage(message)
    .then((response) => sendResponse({ ok: true, ...response }))
    .catch((error) => sendResponse({ ok: false, error: error.message }));

  return true;
});

async function handleMessage(message) {
  switch (message?.action) {
    case 'OFFSCREEN_START_CAPTURE':
      await startCapture(message.payload || {});
      return {};
    case 'OFFSCREEN_STOP_CAPTURE':
      await stopCapture();
      return {};
    default:
      return {};
  }
}

async function startCapture(payload) {
  await stopCapture();

  activeSession = {
    backendUrl: normalizeBackendUrl(payload.backendUrl),
    sessionId: payload.sessionId,
  };

  mediaStream = await navigator.mediaDevices.getUserMedia({
    audio: {
      mandatory: {
        chromeMediaSource: 'tab',
        chromeMediaSourceId: payload.streamId,
      },
    },
    video: false,
  });

  startPcmCapture(mediaStream);
}

async function stopCapture() {
  const sessionToFlush = activeSession;
  activeSession = null;

  if (processorNode) {
    processorNode.disconnect();
  }

  if (sourceNode) {
    sourceNode.disconnect();
  }

  if (silenceGainNode) {
    silenceGainNode.disconnect();
  }

  if (mediaStream) {
    for (const track of mediaStream.getTracks()) {
      track.stop();
    }
  }

  if (audioContext) {
    await audioContext.close().catch(() => {});
  }

  mediaStream = null;
  audioContext = null;
  sourceNode = null;
  processorNode = null;
  silenceGainNode = null;
  chunksSent = 0;
  bytesSent = 0;

  await flushPcmChunk(sessionToFlush);

  pcmParts = [];
  pcmBytes = 0;
}

function startPcmCapture(stream) {
  audioContext = new AudioContext();
  sourceNode = audioContext.createMediaStreamSource(stream);
  processorNode = audioContext.createScriptProcessor(4096, 1, 1);
  silenceGainNode = audioContext.createGain();
  silenceGainNode.gain.value = 0;

  // Keep tab audio audible while the tab capture stream is active.
  sourceNode.connect(audioContext.destination);
  sourceNode.connect(processorNode);
  processorNode.connect(silenceGainNode);
  silenceGainNode.connect(audioContext.destination);

  processorNode.onaudioprocess = (event) => {
    const input = event.inputBuffer.getChannelData(0);
    appendPcm(downsampleToPcm16(input, audioContext.sampleRate, TARGET_SAMPLE_RATE));
  };
}

function appendPcm(pcm) {
  pcmParts.push(pcm);
  pcmBytes += pcm.byteLength;

  if (pcmBytes >= CHUNK_BYTES) {
    flushPcmChunk(activeSession).catch(() => {});
  }
}

async function flushPcmChunk(session = activeSession) {
  if (flushPromise) {
    await flushPromise.catch(() => {});
  }

  if (!session || !pcmBytes) {
    return;
  }

  const chunk = mergeArrayBuffers(pcmParts, pcmBytes);
  pcmParts = [];
  pcmBytes = 0;

  flushPromise = fetchWithTimeout(`${session.backendUrl}/sessions/${session.sessionId}/audio-chunk`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/octet-stream' },
    body: chunk,
  });

  await flushPromise.finally(() => {
    flushPromise = null;
  });

  chunksSent += 1;
  bytesSent += chunk.byteLength;
  await chrome.runtime.sendMessage({
    action: 'CHUNK_SENT',
    payload: {
      chunksSent,
      bytesSent,
    },
  });
}

async function fetchWithTimeout(url, options) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);

  try {
    return await fetch(url, {
      ...options,
      signal: controller.signal,
    });
  } finally {
    clearTimeout(timeoutId);
  }
}

function downsampleToPcm16(input, sourceRate, targetRate) {
  if (targetRate === sourceRate) {
    return floatToPcm16(input);
  }

  const ratio = sourceRate / targetRate;
  const outputLength = Math.floor(input.length / ratio);
  const output = new Float32Array(outputLength);

  for (let index = 0; index < outputLength; index += 1) {
    const start = Math.floor(index * ratio);
    const end = Math.min(Math.floor((index + 1) * ratio), input.length);
    let sum = 0;
    for (let sampleIndex = start; sampleIndex < end; sampleIndex += 1) {
      sum += input[sampleIndex];
    }
    output[index] = sum / Math.max(end - start, 1);
  }

  return floatToPcm16(output);
}

function floatToPcm16(input) {
  const buffer = new ArrayBuffer(input.length * 2);
  const view = new DataView(buffer);

  for (let index = 0; index < input.length; index += 1) {
    const sample = Math.max(-1, Math.min(1, input[index]));
    view.setInt16(index * 2, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
  }

  return buffer;
}

function mergeArrayBuffers(parts, byteLength) {
  const merged = new Uint8Array(byteLength);
  let offset = 0;

  for (const part of parts) {
    merged.set(new Uint8Array(part), offset);
    offset += part.byteLength;
  }

  return merged.buffer;
}

function normalizeBackendUrl(value) {
  return String(value || 'http://127.0.0.1:5010/realtime-audio').replace(/\/+$/, '');
}
