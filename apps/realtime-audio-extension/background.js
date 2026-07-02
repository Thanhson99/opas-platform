let activeCapture = null;
let creatingOffscreenDocument = null;
const EXTENSION_REQUEST_TIMEOUT_MS = 4000;
const BACKEND_FETCH_TIMEOUT_MS = 3000;
const ACTIVE_CAPTURE_STORAGE_KEY = 'activeCapture';

chrome.tabCapture.onStatusChanged.addListener((info) => {
  handleCaptureStatusChanged(info).catch(() => {});
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  handleMessage(message)
    .then((response) => sendResponse({ ok: true, ...response }))
    .catch((error) => sendResponse({ ok: false, error: error.message }));

  return true;
});

async function handleMessage(message) {
  switch (message?.action) {
    case 'LIST_TABS':
      return { tabs: await listTabs() };
    case 'START_CAPTURE':
      return { session: await startCapture(message.payload || {}) };
    case 'START_CURRENT_TAB_CAPTURE':
      return { session: await startCurrentTabCapture(message.payload || {}) };
    case 'STOP_CAPTURE':
      return { session: await stopCapture() };
    case 'GET_STATUS':
      return { activeCapture: await getActiveCapture() };
    case 'OPEN_WEB_UI':
    case 'OPEN_OPAS_UI':
      return { tab: await openWebUi(message.payload || {}) };
    case 'INJECT_WEB_BRIDGE':
    case 'INJECT_OPAS_BRIDGE':
      return { injectedTabs: await injectWebBridge() };
    case 'RELOAD_EXTENSION':
      chrome.runtime.reload();
      return { reloading: true };
    case 'FOCUS_TAB':
      return { tab: await focusTabById(message.payload?.tabId) };
    case 'CHUNK_SENT':
      if (!await getActiveCapture()) {
        return { activeCapture: null };
      }

      await setActiveCapture({
        ...activeCapture,
        chunksSent: message.payload?.chunksSent || activeCapture?.chunksSent || 0,
        bytesSent: message.payload?.bytesSent || activeCapture?.bytesSent || 0,
      });
      return { activeCapture };
    default:
      throw new Error(`Unsupported OPAS extension action: ${message?.action || 'unknown'}`);
  }
}

async function openWebUi(payload) {
  const backendUrl = normalizeBackendUrl(payload.backendUrl);
  const uiUrl = normalizeWebUrl(payload.webUrl) || `${backendUrl}/ui`;
  const existingTab = await findLocalUiTab(uiUrl);
  if (existingTab?.id) {
    await focusTab(existingTab);
    await injectWebBridge(existingTab.id);
    return existingTab;
  }

  const tab = await chrome.tabs.create({ url: uiUrl });
  return tab;
}

async function findLocalUiTab(uiUrl) {
  const tabs = await chrome.tabs.query({
    url: [
      'http://127.0.0.1/*',
      'http://localhost/*',
    ],
  });

  return tabs.find((tab) => tab.id && tab.url && tab.url.startsWith(uiUrl)) || null;
}

async function injectWebBridge(tabId = null) {
  if (!chrome.scripting) {
    throw new Error('Chrome scripting API is not available. Reload the extension.');
  }

  const tabs = tabId
    ? [await chrome.tabs.get(tabId)]
    : await chrome.tabs.query({
      url: [
        'http://127.0.0.1/*',
        'http://localhost/*',
      ],
    });
  const injectedTabs = [];

  for (const tab of tabs) {
    if (!tab?.id || !tab.url || !isWebBridgeTarget(tab.url)) {
      continue;
    }

    await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      files: ['content.js'],
    }).catch(() => {});
    injectedTabs.push(tab.id);
  }

  return injectedTabs;
}

async function startCurrentTabCapture(payload) {
  const backendUrl = normalizeBackendUrl(payload.backendUrl);
  const tab = await currentActiveTab();
  if (!tab?.id) {
    throw new Error('Open this popup on a normal browser tab before connecting capture.');
  }

  return startCapture({
    sourceId: `extension-tab:${tab.id}`,
    backendUrl,
  });
}

async function currentActiveTab() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true }).catch(() => []);
  if (!isCaptureCandidateTab(tab)) {
    return null;
  }

  return tab;
}

function isCaptureCandidateTab(tab) {
  return Boolean(
    tab?.id &&
    tab.url &&
    /^https?:\/\//.test(tab.url),
  );
}

async function listTabs() {
  const tabs = await chrome.tabs.query({
    windowType: 'normal',
  });
  const [activeTab] = await chrome.tabs.query({ active: true, currentWindow: true }).catch(() => []);

  return tabs
    .filter(isCaptureCandidateTab)
    .map((tab, index) => ({
      source_id: `extension-tab:${tab.id}`,
      extension_tab_id: tab.id,
      browser: extensionBrowserName(),
      window_index: tab.windowId || 0,
      tab_index: index + 1,
      title: tab.title || tab.url || `Tab ${tab.id}`,
      url: tab.url || '',
      is_capture_ready: true,
      setup_hint: 'Ready for extension tab audio capture.',
      capture_provider: 'extension',
      audible: Boolean(tab.audible),
      active: tab.id === activeTab?.id,
    }));
}

async function startCapture(payload) {
  const backendUrl = normalizeBackendUrl(payload.backendUrl);
  const tabId = parseExtensionTabId(payload.sourceId);
  const tab = await chrome.tabs.get(tabId);
  const currentCapture = await getActiveCapture();

  await assertBackendReady(backendUrl);
  if (currentCapture?.sessionId) {
    await stopCapture();
  }

  const session = await createSession(backendUrl, tab);
  await focusTab(tab);
  const streamId = await getMediaStreamIdForTab(tabId).catch(async (error) => {
    await stopBackendSession(backendUrl, session.session_id);
    await focusTab(tab);
    throw new Error(activeTabPermissionMessage(error.message));
  });
  await ensureOffscreenDocument();

  const response = await chrome.runtime.sendMessage({
    action: 'OFFSCREEN_START_CAPTURE',
    payload: {
      backendUrl,
      sessionId: session.session_id,
      streamId,
    },
  });

  if (!response?.ok) {
    throw new Error(response?.error || 'Offscreen capture did not start.');
  }

  await setActiveCapture({
    sessionId: session.session_id,
    tabId,
    title: tab.title || tab.url || `Tab ${tabId}`,
    backendUrl,
    chunksSent: 0,
    bytesSent: 0,
  });

  return session;
}

async function handleCaptureStatusChanged(info) {
  const currentCapture = await getActiveCapture();
  if (!currentCapture || info.tabId !== currentCapture.tabId) {
    return;
  }

  if (!['stopped', 'error'].includes(info.status)) {
    return;
  }

  await clearActiveCapture();
  await closeOffscreenDocument();
  await stopBackendSession(currentCapture.backendUrl, currentCapture.sessionId);
}

async function focusTabById(tabId) {
  const tab = await chrome.tabs.get(Number(tabId || 0));
  if (!isCaptureCandidateTab(tab)) {
    throw new Error('This browser page cannot be opened for capture.');
  }

  await focusTab(tab);
  return tab;
}

async function focusTab(tab) {
  if (tab.windowId) {
    await chrome.windows.update(tab.windowId, { focused: true }).catch(() => {});
  }

  if (tab.id) {
    await chrome.tabs.update(tab.id, { active: true }).catch(() => {});
  }
}

function activeTabPermissionMessage(message) {
  if (String(message).includes('Extension has not been invoked')) {
    return (
      'Chrome requires the OPAS extension to be invoked on the tab before audio capture. ' +
      'The selected tab was focused. Click the extension icon again on that tab, keep it selected, then press Connect.'
    );
  }

  return message;
}

async function stopCapture() {
  const currentCapture = await getActiveCapture();
  if (!currentCapture?.sessionId) {
    await clearActiveCapture();
    await closeOffscreenDocument();
    return null;
  }

  await clearActiveCapture();

  await Promise.allSettled([
    withTimeout(
      chrome.runtime.sendMessage({
        action: 'OFFSCREEN_STOP_CAPTURE',
      }),
      EXTENSION_REQUEST_TIMEOUT_MS,
    ),
    stopBackendSession(currentCapture.backendUrl, currentCapture.sessionId),
  ]);
  await closeOffscreenDocument();

  return null;
}

async function stopBackendSession(backendUrl, sessionId) {
  await fetchWithTimeout(`${backendUrl}/sessions/${sessionId}/stop`, {
    method: 'POST',
  }).catch(() => {});
}

async function fetchWithTimeout(url, options) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), BACKEND_FETCH_TIMEOUT_MS);

  try {
    return await fetch(url, {
      ...options,
      signal: controller.signal,
    });
  } finally {
    clearTimeout(timeoutId);
  }
}

function withTimeout(promise, milliseconds) {
  return Promise.race([
    promise,
    new Promise((_, reject) => {
      setTimeout(() => reject(new Error('Extension request timed out.')), milliseconds);
    }),
  ]);
}

async function assertBackendReady(backendUrl) {
  let response;
  try {
    response = await fetch(`${backendUrl}/health`);
  } catch (error) {
    throw new Error(
      `Local service is not running at ${backendUrl}. Run scripts/start-realtime-audio-capture.sh, then reload this web page. ` +
      'If you have two Chrome apps/profiles open, load this extension in the same Chrome profile that contains the tab you want to capture.'
    );
  }

  if (!response.ok) {
    throw new Error(`Backend health check failed: HTTP ${response.status}.`);
  }
}

async function createSession(backendUrl, tab) {
  const response = await fetch(`${backendUrl}/sessions`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      source_id: `extension-tab:${tab.id}`,
      source_label: tab.title || tab.url || `Tab ${tab.id}`,
      source_url: tab.url || '',
      source_browser: extensionBrowserName(),
      audio_format: {
        format: 'pcm_s16le',
        sample_rate: 16000,
        channels: 1,
        chunk_ms: 250,
      },
    }),
  });

  if (!response.ok) {
    throw new Error(await response.text());
  }

  return response.json();
}

async function getMediaStreamIdForTab(tabId) {
  return getMediaStreamId({ targetTabId: tabId });
}

function getMediaStreamId(options) {
  return new Promise((resolve, reject) => {
    chrome.tabCapture.getMediaStreamId(options, (streamId) => {
      const error = chrome.runtime.lastError;
      if (error) {
        reject(new Error(error.message));
        return;
      }

      if (!streamId) {
        reject(new Error('Chrome did not return a tab media stream id.'));
        return;
      }

      resolve(streamId);
    });
  });
}

async function ensureOffscreenDocument() {
  if (!chrome.offscreen) {
    throw new Error('Chrome offscreen documents are not available in this browser.');
  }

  if (await chrome.offscreen.hasDocument()) {
    return;
  }

  if (!creatingOffscreenDocument) {
    creatingOffscreenDocument = chrome.offscreen.createDocument({
      url: 'offscreen.html',
      reasons: ['USER_MEDIA'],
      justification: 'Record selected tab audio and forward chunks to the local OPAS realtime audio service.',
    });
  }

  try {
    await creatingOffscreenDocument;
  } finally {
    creatingOffscreenDocument = null;
  }
}

async function closeOffscreenDocument() {
  if (!chrome.offscreen || !(await chrome.offscreen.hasDocument())) {
    return;
  }

  await chrome.offscreen.closeDocument().catch(() => {});
}

async function getActiveCapture() {
  if (activeCapture) {
    return activeCapture;
  }

  const stored = await chrome.storage.session.get(ACTIVE_CAPTURE_STORAGE_KEY).catch(() => ({}));
  activeCapture = stored[ACTIVE_CAPTURE_STORAGE_KEY] || null;
  return activeCapture;
}

async function setActiveCapture(capture) {
  activeCapture = capture;
  await chrome.storage.session.set({ [ACTIVE_CAPTURE_STORAGE_KEY]: capture }).catch(() => {});
}

async function clearActiveCapture() {
  activeCapture = null;
  await chrome.storage.session.remove(ACTIVE_CAPTURE_STORAGE_KEY).catch(() => {});
}

function parseExtensionTabId(sourceId) {
  const match = String(sourceId || '').match(/^extension-tab:(\d+)$/);
  if (!match) {
    throw new Error('Select a browser tab from this extension popup.');
  }

  return Number(match[1]);
}

function normalizeBackendUrl(value) {
  return String(value || 'http://127.0.0.1:5010/realtime-audio').replace(/\/+$/, '');
}

function normalizeWebUrl(value) {
  return value ? String(value).replace(/\/+$/, '') : '';
}

function isWebBridgeTarget(url) {
  return url.includes('/realtime-audio/ui') || url.includes('/realtime-translate');
}

function extensionBrowserName() {
  const userAgent = navigator.userAgent;
  if (userAgent.includes('Edg/')) {
    return 'Microsoft Edge Extension';
  }

  if (userAgent.includes('Brave')) {
    return 'Brave Extension';
  }

  return 'Chromium Extension';
}
