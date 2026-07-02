"""Standalone HTML UI renderer kept as a fallback to the Laravel screen."""

def render_capture_ui() -> str:
    """Render the fallback standalone realtime audio UI."""
    return """<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OPAS Realtime Audio</title>
  <style>
    :root {
      color-scheme: dark;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #101319;
      color: #f3f6fb;
    }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; background: #101319; }
    main { width: min(1180px, calc(100vw - 28px)); margin: 0 auto; padding: 18px 0 28px; }
    header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 14px; }
    h1 { margin: 0; font-size: 22px; line-height: 1.2; }
    h2 { margin: 0 0 10px; font-size: 14px; line-height: 1.2; color: #dbe4ef; }
    button, select {
      min-height: 36px;
      border: 1px solid #334155;
      border-radius: 6px;
      background: #1a2330;
      color: #f8fafc;
      font: inherit;
    }
    button { cursor: pointer; font-weight: 750; padding: 0 12px; }
    button.primary { border-color: #0f766e; background: #0f766e; }
    button.danger { border-color: #7f1d1d; background: #7f1d1d; }
    button:disabled, select:disabled { opacity: .55; cursor: not-allowed; }
    select { width: 100%; padding: 0 10px; }
    textarea {
      width: 100%;
      min-height: 74px;
      resize: vertical;
      border: 1px solid #334155;
      border-radius: 6px;
      background: #101722;
      color: #f8fafc;
      font: inherit;
      padding: 8px 10px;
    }
    .grid { display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 14px; align-items: start; }
    .panel { border: 1px solid #263244; background: #171c25; border-radius: 8px; padding: 12px; }
    .stack { display: grid; gap: 10px; }
    .toolbar { display: flex; gap: 8px; flex-wrap: wrap; }
    .toolbar button { flex: 1 1 120px; }
    .status { color: #a8b4c5; font-size: 13px; line-height: 1.5; overflow-wrap: anywhere; white-space: pre-line; }
    .meta { color: #91a0b4; font-size: 12px; line-height: 1.45; overflow-wrap: anywhere; white-space: pre-line; }
    .metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
    .metric { border: 1px solid #263244; background: #101722; border-radius: 6px; padding: 8px; }
    .metric span { display: block; color: #8ea0b7; font-size: 10px; text-transform: uppercase; }
    .metric strong { display: block; margin-top: 3px; color: #f8fafc; font-size: 13px; overflow: hidden; text-overflow: ellipsis; }
    .waterfall {
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      gap: 8px;
      height: 440px;
      overflow: hidden;
      border: 1px solid #263244;
      border-radius: 8px;
      background: #0d1117;
      padding: 12px;
    }
    .line { display: grid; gap: 5px; border-left: 3px solid #22d3ee; border-radius: 5px; background: #141b25; padding: 9px 11px; }
    .line:nth-last-child(n+7) { opacity: .25; }
    .line:nth-last-child(6) { opacity: .42; }
    .line:nth-last-child(5) { opacity: .58; }
    .line:nth-last-child(4) { opacity: .72; }
    .speaker { color: #67e8f9; font-size: 11px; font-weight: 850; text-transform: uppercase; }
    .line-meta { color: #8ea0b7; font-size: 11px; }
    .text { color: #f8fafc; font-size: 15px; line-height: 1.45; }
    .translation { color: #bbf7d0; font-size: 14px; line-height: 1.45; }
    .empty { color: #8b98aa; text-align: center; padding: 34px 20px; }
    .loading {
      display: grid;
      gap: 8px;
      justify-items: center;
      color: #dbeafe;
      text-align: center;
      padding: 34px 20px;
    }
    .spinner {
      width: 28px;
      height: 28px;
      border: 3px solid #263244;
      border-top-color: #22d3ee;
      border-radius: 999px;
      animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .notice { border: 1px solid #4a3d1d; background: #1e1a10; color: #f3d08a; border-radius: 6px; padding: 9px 10px; font-size: 12px; line-height: 1.45; }
    .checks { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .check { display: flex; align-items: center; gap: 7px; min-height: 32px; color: #dce5f1; font-size: 13px; }
    .check input { accent-color: #0f766e; }
    @media (max-width: 860px) {
      main { width: min(100vw - 18px, 680px); }
      header { align-items: flex-start; flex-direction: column; }
      .grid { grid-template-columns: 1fr; }
      .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .waterfall { height: 360px; }
    }
  </style>
</head>
<body>
  <main>
    <header>
      <h1>OPAS Realtime Audio</h1>
      <div class="toolbar">
        <button id="refreshButton" type="button">Refresh</button>
        <button id="warmButton" type="button">Warm STT</button>
      </div>
    </header>

    <section class="grid">
      <aside class="panel stack">
        <h2>Connection</h2>
        <div id="serviceStatus" class="status">Checking service...</div>
        <div id="extensionStatus" class="status">Checking extension...</div>
        <select id="tabSelect" aria-label="Browser tab"></select>
        <div id="tabMeta" class="meta">No tab selected.</div>
        <div class="toolbar">
          <button id="focusButton" type="button">Open Tab</button>
          <button id="connectButton" class="primary" type="button">Connect</button>
          <button id="stopButton" class="danger" type="button" disabled>Stop</button>
        </div>
        <div class="notice">
          If you have two Chrome apps or profiles, load this extension in the same Chrome profile that contains the tab you want to capture. The web screen can only see tabs from that extension instance.
        </div>
        <h2>Speech To Text</h2>
        <select id="sttProfileSelect" aria-label="STT profile">
          <option value="live">Live - balanced latency</option>
          <option value="fast">Fast - lower accuracy</option>
          <option value="balanced">Balanced - better accuracy</option>
          <option value="accurate">Accurate - slower, best quality</option>
        </select>
        <div id="languageChecks" class="checks" aria-label="Accepted speech languages">
          <label class="check"><input type="checkbox" value="vi"> Vietnamese</label>
          <label class="check"><input type="checkbox" value="en"> English</label>
          <label class="check"><input type="checkbox" value="ja"> Japanese</label>
          <label class="check"><input type="checkbox" value="zh"> Chinese</label>
        </div>
        <div class="meta">Leave all unchecked to accept every detected language. Check English + Vietnamese to ignore Japanese/Chinese audio.</div>
        <textarea id="promptInput" maxlength="600" aria-label="Vocabulary and context prompt" placeholder="Vocabulary/context: product names, people names, technical terms..."></textarea>
        <div class="meta">Use this for words Whisper often gets wrong, for example: OpenAI, Laravel, n8n, product names, customer names.</div>
        <div id="sttConfigStatus" class="meta">Loading STT config...</div>
        <h2>Translate</h2>
        <select id="translateSelect" aria-label="Translation mode">
          <option value="off">Transcript only</option>
          <option value="vi">Show Vietnamese when available</option>
          <option value="en">Show English when available</option>
        </select>
        <div class="meta">Translation provider is not wired in this simplified pass; the UI is ready to show translated_text when the backend returns it.</div>
      </aside>

      <section class="panel stack">
        <h2>Realtime Text</h2>
        <div class="metrics">
          <div class="metric"><span>Session</span><strong id="sessionMetric">idle</strong></div>
          <div class="metric"><span>Chunks</span><strong id="chunksMetric">0</strong></div>
          <div class="metric"><span>Audio</span><strong id="bytesMetric">0 KB</strong></div>
          <div class="metric"><span>STT</span><strong id="sttMetric">idle</strong></div>
        </div>
        <div id="transcriptNotes" class="status">No audio session yet.</div>
        <div id="waterfall" class="waterfall" aria-live="polite">
          <div class="empty">Choose a tab and connect.</div>
        </div>
      </section>
    </section>
  </main>

  <script>
    const BACKEND_URL = '/realtime-audio';
    const MAX_LINES = 12;

    const state = {
      extensionReady: false,
      tabs: [],
      selectedSourceId: null,
      activeSessionId: null,
      transcriptSocket: null,
      transcriptTimer: null,
      statusTimer: null,
      lines: [],
      translateMode: 'off',
      sttConfig: null,
      sttConfigTimer: null,
      warmupStarted: false,
      preparingCapture: false,
    };

    const serviceStatus = document.getElementById('serviceStatus');
    const extensionStatus = document.getElementById('extensionStatus');
    const tabSelect = document.getElementById('tabSelect');
    const tabMeta = document.getElementById('tabMeta');
    const refreshButton = document.getElementById('refreshButton');
    const warmButton = document.getElementById('warmButton');
    const focusButton = document.getElementById('focusButton');
    const connectButton = document.getElementById('connectButton');
    const stopButton = document.getElementById('stopButton');
    const sttProfileSelect = document.getElementById('sttProfileSelect');
    const languageChecks = Array.from(document.querySelectorAll('#languageChecks input[type="checkbox"]'));
    const promptInput = document.getElementById('promptInput');
    const sttConfigStatus = document.getElementById('sttConfigStatus');
    const translateSelect = document.getElementById('translateSelect');
    const sessionMetric = document.getElementById('sessionMetric');
    const chunksMetric = document.getElementById('chunksMetric');
    const bytesMetric = document.getElementById('bytesMetric');
    const sttMetric = document.getElementById('sttMetric');
    const transcriptNotes = document.getElementById('transcriptNotes');
    const waterfall = document.getElementById('waterfall');

    refreshButton.addEventListener('click', loadAll);
    warmButton.addEventListener('click', warmStt);
    focusButton.addEventListener('click', focusSelectedTab);
    connectButton.addEventListener('click', connectSelectedTab);
    stopButton.addEventListener('click', stopCapture);
    sttProfileSelect.addEventListener('change', saveSttConfig);
    for (const checkbox of languageChecks) {
      checkbox.addEventListener('change', saveSttConfig);
    }
    promptInput.addEventListener('blur', saveSttConfig);
    promptInput.addEventListener('keydown', (event) => {
      if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        saveSttConfig();
      }
    });
    tabSelect.addEventListener('change', () => {
      state.selectedSourceId = tabSelect.value;
      renderSelectedTab();
      renderControls();
    });
    translateSelect.addEventListener('change', () => {
      state.translateMode = translateSelect.value;
      renderWaterfall();
    });

    window.addEventListener('message', (event) => {
      if (event.source !== window || event.data?.source !== 'opas-realtime-audio-extension') {
        return;
      }

      if (event.data.type === 'READY' || event.data.type === 'PONG') {
        state.extensionReady = true;
        extensionStatus.textContent = 'Extension bridge: connected.';
        return;
      }
    });

    loadAll();

    async function loadAll() {
      await Promise.all([loadHealth(), loadSttConfig(), pingExtension()]);
      await autoWarmStt();
      if (state.extensionReady) {
        await loadTabs();
        await syncStatus();
      }
    }

    async function loadHealth() {
      try {
        const response = await fetch(`${BACKEND_URL}/health`);
        const payload = await response.json();
        serviceStatus.textContent = `Service: running | STT: ${payload.stt_status}`;
      } catch (error) {
        serviceStatus.textContent = 'Service: not reachable. Run scripts/start-realtime-audio-capture.sh, then refresh.';
      }
    }

    async function loadSttConfig() {
      try {
        const response = await fetch(`${BACKEND_URL}/stt/config`);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        state.sttConfig = payload;
        sttProfileSelect.value = payload.profile;
        setSelectedLanguages(payload.languages || []);
        promptInput.value = payload.prompt || '';
        renderSttConfig(payload);
        watchSttConfigIfLoading(payload);
      } catch (error) {
        sttConfigStatus.textContent = `STT config unavailable: ${friendlyError(error.message)}`;
      }
    }

    async function saveSttConfig() {
      try {
        const response = await fetch(`${BACKEND_URL}/stt/config`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            profile: sttProfileSelect.value,
            languages: selectedLanguages(),
            prompt: promptInput.value,
          }),
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        state.sttConfig = payload;
        renderSttConfig(payload);
        watchSttConfigIfLoading(payload);
        if (!payload.model_loaded && !payload.model_loading) {
          autoWarmStt();
        }
        serviceStatus.textContent = `Service: running | ${payload.message}`;
      } catch (error) {
        sttConfigStatus.textContent = `Save STT config failed: ${friendlyError(error.message)}`;
      }
    }

    function renderSttConfig(payload) {
      const modelState = payload.model_loaded ? 'loaded' : (payload.model_loading ? 'loading' : 'lazy');
      sttConfigStatus.textContent = [
        `profile=${payload.profile}`,
        `model=${payload.model}`,
        `accept=${payload.languages?.length ? payload.languages.join(',') : 'all'}`,
        `mode=${payload.language === 'auto' ? 'auto-detect' : `force-${payload.language}`}`,
        `beam=${payload.beam_size}`,
        `buffer=${Math.round(payload.max_buffer_bytes / 32000)}s`,
        payload.normalize_audio ? 'normalize=on' : 'normalize=off',
        payload.prompt ? 'prompt=on' : 'prompt=off',
        payload.auto_warmup ? 'warmup=auto' : 'warmup=manual',
        `lang-threshold=${payload.language_confidence_threshold}`,
        modelState,
      ].join(' | ');
    }

    async function autoWarmStt() {
      if (state.warmupStarted || state.sttConfig?.model_loaded || state.sttConfig?.model_loading) {
        return;
      }
      state.warmupStarted = true;
      await warmStt({ quiet: true });
    }

    function watchSttConfigIfLoading(payload) {
      if (payload?.model_loaded || !payload?.model_loading) {
        if (state.sttConfigTimer) {
          clearInterval(state.sttConfigTimer);
          state.sttConfigTimer = null;
        }
        return;
      }

      if (state.sttConfigTimer) {
        return;
      }

      state.sttConfigTimer = setInterval(loadSttConfig, 1500);
    }

    function selectedLanguages() {
      return languageChecks
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.value);
    }

    function setSelectedLanguages(languages) {
      const selected = new Set(languages || []);
      for (const checkbox of languageChecks) {
        checkbox.checked = selected.has(checkbox.value);
      }
    }

    async function pingExtension() {
      try {
        await extensionRequest('GET_STATUS', {}, 900);
        state.extensionReady = true;
        extensionStatus.textContent = 'Extension bridge: connected.';
      } catch (error) {
        extensionStatus.textContent = 'Extension bridge: not detected. Click the extension icon, press Refresh, then reload this page.';
      }
    }

    async function loadTabs() {
      try {
        const response = await extensionRequest('LIST_TABS');
        state.tabs = response.tabs || [];
        const activeTab = state.tabs.find((tab) => tab.active) || state.tabs[0] || null;
        if (!state.selectedSourceId || !state.tabs.some((tab) => tab.source_id === state.selectedSourceId)) {
          state.selectedSourceId = activeTab?.source_id || '';
        }
        renderTabs();
      } catch (error) {
        extensionStatus.textContent = `Extension bridge error: ${friendlyError(error.message)}`;
        renderTabs();
      }
    }

    function renderTabs() {
      tabSelect.textContent = '';
      if (!state.tabs.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No extension tabs found';
        tabSelect.append(option);
        tabSelect.disabled = true;
        tabMeta.textContent = 'Open the target tab in this same Chrome profile, then refresh.';
        renderControls();
        return;
      }

      tabSelect.disabled = false;
      for (const tab of state.tabs) {
        const option = document.createElement('option');
        option.value = tab.source_id;
        option.textContent = `${tab.active ? '[current] ' : ''}${tab.title}`;
        tabSelect.append(option);
      }
      tabSelect.value = state.selectedSourceId;
      renderSelectedTab();
      renderControls();
    }

    function renderSelectedTab() {
      const tab = selectedTab();
      if (!tab) {
        tabMeta.textContent = 'No tab selected.';
        return;
      }

      tabMeta.textContent = [
        `${tab.browser} | window ${tab.window_index} | tab ${tab.tab_index}`,
        tab.audible ? 'Audio detected by Chrome.' : 'Audio not detected yet.',
        tab.url,
      ].join('\\n');
    }

    async function focusSelectedTab() {
      const tab = selectedTab();
      if (!tab) {
        return;
      }
      await extensionRequest('FOCUS_TAB', { tabId: tab.extension_tab_id }).catch((error) => {
        extensionStatus.textContent = `Open tab failed: ${friendlyError(error.message)}`;
      });
    }

    async function connectSelectedTab() {
      const tab = selectedTab();
      if (!tab) {
        extensionStatus.textContent = 'Select a tab first.';
        return;
      }

      renderBusy(true);
      extensionStatus.textContent = `Connecting ${tab.title}...`;
      state.preparingCapture = true;
      renderLoading('Preparing STT model before capture...');
      try {
        await ensureSttReady();
        renderLoading('Starting tab audio capture...');
        const response = await extensionRequest('START_CAPTURE', {
          backendUrl: absoluteBackendUrl(),
          sourceId: tab.source_id,
        });
        state.activeSessionId = response.session?.session_id || null;
        state.lines = [];
        extensionStatus.textContent = `Connected: ${tab.title}`;
        startTranscriptWatch();
        startStatusWatch();
      } catch (error) {
        extensionStatus.textContent = `Connect failed: ${friendlyError(error.message)}`;
      } finally {
        state.preparingCapture = false;
        renderBusy(false);
        renderControls();
      }
    }

    async function ensureSttReady() {
      let config = state.sttConfig;
      if (config?.model_loaded) {
        return config;
      }

      extensionStatus.textContent = 'Preparing STT model. Capture will start after the model is ready.';
      await warmStt({ quiet: true });

      const startedAt = Date.now();
      while (Date.now() - startedAt < 120000) {
        config = await fetchSttConfig();
        state.sttConfig = config;
        renderSttConfig(config);
        if (config.model_loaded) {
          extensionStatus.textContent = 'STT model ready. Starting capture...';
          return config;
        }
        if (!config.model_loading) {
          await warmStt({ quiet: true });
        }
        await delay(1200);
      }

      throw new Error('STT model did not finish loading in time. Check the backend terminal log.');
    }

    async function stopCapture() {
      await extensionRequest('STOP_CAPTURE').catch((error) => {
        extensionStatus.textContent = `Stop failed: ${friendlyError(error.message)}`;
      });
      state.activeSessionId = null;
      stopTranscriptWatch();
      stopStatusWatch();
      renderControls();
      renderWaterfall();
    }

    async function warmStt(options = {}) {
      try {
        const response = await fetch(`${BACKEND_URL}/stt/warmup`, { method: 'POST' });
        const payload = await response.json();
        serviceStatus.textContent = `Service: running | ${payload.message}`;
        await loadSttConfig();
      } catch (error) {
        if (!options.quiet) {
          serviceStatus.textContent = `Warm STT failed: ${friendlyError(error.message)}`;
        }
      }
    }

    async function fetchSttConfig() {
      const response = await fetch(`${BACKEND_URL}/stt/config`);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    }

    function startTranscriptWatch() {
      stopTranscriptWatch();
      openTranscriptSocket();
      state.transcriptTimer = setInterval(fetchTranscript, 1200);
      fetchTranscript();
    }

    function stopTranscriptWatch() {
      if (state.transcriptSocket) {
        state.transcriptSocket.close();
      }
      if (state.transcriptTimer) {
        clearInterval(state.transcriptTimer);
      }
      state.transcriptSocket = null;
      state.transcriptTimer = null;
    }

    function openTranscriptSocket() {
      if (!state.activeSessionId) {
        return;
      }
      const socketUrl = `${absoluteBackendUrl().replace(/^http/, 'ws')}/sessions/${state.activeSessionId}/transcript/ws`;
      state.transcriptSocket = new WebSocket(socketUrl);
      state.transcriptSocket.onmessage = (event) => applyTranscript(JSON.parse(event.data));
      state.transcriptSocket.onclose = () => { state.transcriptSocket = null; };
    }

    async function fetchTranscript() {
      if (!state.activeSessionId) {
        return;
      }
      const response = await fetch(`${BACKEND_URL}/sessions/${state.activeSessionId}/transcript`);
      if (!response.ok) {
        return;
      }
      applyTranscript(await response.json());
    }

    function applyTranscript(payload) {
      sttMetric.textContent = payload.status || 'waiting';
      transcriptNotes.textContent = (payload.notes || []).slice(0, 4).join('\\n') || 'Listening for speech...';
      const lines = (payload.lines || [])
        .map((line) => ({
          key: `${line.started_at || ''}:${line.original_text || ''}`,
          speaker: line.speaker || 'Speaker',
          text: line.original_text || '',
          translatedText: line.translated_text || '',
          detectedLanguage: line.detected_language || '',
          confidence: line.confidence,
        }))
        .filter((line) => line.text.trim());
      state.lines = dedupe([...state.lines, ...lines]).slice(-MAX_LINES);
      renderWaterfall();
    }

    function startStatusWatch() {
      stopStatusWatch();
      state.statusTimer = setInterval(syncStatus, 900);
      syncStatus();
    }

    function stopStatusWatch() {
      if (state.statusTimer) {
        clearInterval(state.statusTimer);
      }
      state.statusTimer = null;
    }

    async function syncStatus() {
      const response = await extensionRequest('GET_STATUS').catch(() => null);
      const capture = response?.activeCapture;
      if (!capture) {
        chunksMetric.textContent = '0';
        bytesMetric.textContent = '0 KB';
        if (state.activeSessionId) {
          state.activeSessionId = null;
          stopTranscriptWatch();
          stopStatusWatch();
          extensionStatus.textContent = 'Capture stopped.';
          renderControls();
        }
        return;
      }

      state.activeSessionId = capture.sessionId;
      sessionMetric.textContent = capture.sessionId.slice(0, 8);
      chunksMetric.textContent = String(capture.chunksSent || 0);
      bytesMetric.textContent = formatBytes(capture.bytesSent || 0);
      renderControls();
    }

    function renderWaterfall() {
      waterfall.textContent = '';
      if (state.preparingCapture) {
        renderLoading('Preparing STT model before capture...');
        return;
      }
      if (!state.lines.length) {
        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = state.activeSessionId ? 'Listening for speech...' : 'Choose a tab and connect.';
        waterfall.append(empty);
        sessionMetric.textContent = state.activeSessionId ? state.activeSessionId.slice(0, 8) : 'idle';
        return;
      }

      for (const line of state.lines) {
        const item = document.createElement('article');
        item.className = 'line';
        const speaker = document.createElement('div');
        speaker.className = 'speaker';
        speaker.textContent = line.speaker;
        const meta = document.createElement('div');
        meta.className = 'line-meta';
        meta.textContent = line.detectedLanguage
          ? `detected=${line.detectedLanguage}${typeof line.confidence === 'number' ? ` | confidence=${line.confidence.toFixed(2)}` : ''}`
          : 'detected=unknown';
        const text = document.createElement('div');
        text.className = 'text';
        text.textContent = line.text;
        item.append(speaker, meta, text);
        if (state.translateMode !== 'off' && line.translatedText) {
          const translated = document.createElement('div');
          translated.className = 'translation';
          translated.textContent = line.translatedText;
          item.append(translated);
        }
        waterfall.append(item);
      }
    }

    function renderLoading(message) {
      waterfall.textContent = '';
      const loading = document.createElement('div');
      loading.className = 'loading';
      const spinner = document.createElement('div');
      spinner.className = 'spinner';
      const text = document.createElement('div');
      text.textContent = message;
      loading.append(spinner, text);
      waterfall.append(loading);
      sttMetric.textContent = 'loading';
      transcriptNotes.textContent = 'Waiting for the local STT model to be ready before capturing tab audio.';
    }

    function renderControls() {
      const connected = Boolean(state.activeSessionId);
      const hasTab = Boolean(selectedTab());
      focusButton.disabled = !hasTab;
      connectButton.disabled = connected || !hasTab || !state.extensionReady;
      stopButton.disabled = !connected;
    }

    function renderBusy(isBusy) {
      refreshButton.disabled = isBusy;
      warmButton.disabled = isBusy;
      focusButton.disabled = isBusy;
      connectButton.disabled = isBusy;
      stopButton.disabled = isBusy;
    }

    function selectedTab() {
      return state.tabs.find((tab) => tab.source_id === state.selectedSourceId) || null;
    }

    function dedupe(lines) {
      const keys = new Set();
      return lines.filter((line) => {
        if (keys.has(line.key)) {
          return false;
        }
        keys.add(line.key);
        return true;
      });
    }

    function extensionRequest(action, payload = {}, timeoutMs = 5000) {
      return new Promise((resolve, reject) => {
        const requestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const timeout = setTimeout(() => {
          window.removeEventListener('message', onMessage);
          reject(new Error('Extension bridge did not respond. Reload the extension and press Refresh in the popup.'));
        }, timeoutMs);

        function onMessage(event) {
          if (
            event.source !== window ||
            event.data?.source !== 'opas-realtime-audio-extension' ||
            event.data?.type !== 'RESPONSE' ||
            event.data?.requestId !== requestId
          ) {
            return;
          }

          clearTimeout(timeout);
          window.removeEventListener('message', onMessage);
          if (!event.data.ok) {
            reject(new Error(event.data.error || 'Extension request failed.'));
            return;
          }
          resolve(event.data.response || {});
        }

        window.addEventListener('message', onMessage);
        window.postMessage(
          {
            source: 'opas-realtime-audio-ui',
            type: 'REQUEST',
            requestId,
            action,
            payload,
          },
          '*',
        );
      });
    }

    function absoluteBackendUrl() {
      return `${window.location.origin}${BACKEND_URL}`;
    }

    function friendlyError(message) {
      if (message.includes('Local service is not running') || message.includes('Failed to fetch')) {
        return 'Local service is not running. Run scripts/start-realtime-audio-capture.sh in Terminal, then refresh this page.';
      }
      if (message.includes('Extension has not been invoked')) {
        return 'Chrome needs one more click on the extension icon for the selected tab. The tab was focused; click the extension icon there, press Open Web, then Connect again.';
      }
      return message;
    }

    function formatBytes(bytes) {
      if (bytes < 1024) {
        return `${bytes} B`;
      }
      return `${Math.round(bytes / 1024)} KB`;
    }

    function delay(ms) {
      return new Promise((resolve) => setTimeout(resolve, ms));
    }
  </script>
</body>
</html>"""
