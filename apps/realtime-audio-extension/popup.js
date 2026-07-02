const DEFAULT_BACKEND_URL = 'http://127.0.0.1:5010/realtime-audio';
const DEFAULT_WEB_URL = 'http://127.0.0.1:8881/realtime-translate';

const openWebButton = document.getElementById('openWebButton');
const refreshBridgeButton = document.getElementById('refreshBridgeButton');
const statusBox = document.getElementById('status');

openWebButton.addEventListener('click', openWeb);
refreshBridgeButton.addEventListener('click', refreshBridge);

async function openWeb() {
  setBusy(true);
  setStatus('Opening web screen...');
  try {
    await sendExtensionMessageWithFallback(
      ['OPEN_WEB_UI', 'OPEN_OPAS_UI'],
      { backendUrl: DEFAULT_BACKEND_URL, webUrl: DEFAULT_WEB_URL },
    );
    setStatus('Web screen opened. Use it to choose tab, connect, and view text.');
  } catch (error) {
    setStatus(`Open failed: ${friendlyError(error.message)}`);
  } finally {
    setBusy(false);
  }
}

async function refreshBridge() {
  setBusy(true);
  setStatus('Refreshing extension bridge...');
  try {
    const response = await sendExtensionMessageWithFallback(
      ['INJECT_WEB_BRIDGE', 'INJECT_OPAS_BRIDGE'],
      {},
    );
    const count = response.injectedTabs?.length || 0;
    setStatus(count ? `Bridge refreshed in ${count} web tab(s).` : 'Open the web screen first, then refresh.');
  } catch (error) {
    if (isUnsupportedActionError(error.message)) {
      setStatus('Extension code is stale. Reloading extension; reopen the popup after it closes.');
      chrome.runtime.reload();
      return;
    }

    setStatus(`Refresh failed: ${friendlyError(error.message)}`);
  } finally {
    setBusy(false);
  }
}

function setBusy(isBusy) {
  openWebButton.disabled = isBusy;
  refreshBridgeButton.disabled = isBusy;
}

function setStatus(message) {
  statusBox.textContent = message;
}

function friendlyError(message) {
  if (isUnsupportedActionError(message)) {
    return 'Extension background is stale. Reload the extension in chrome://extensions, then press Open Web again.';
  }

  if (message.includes('Failed to fetch') || message.includes('not running')) {
    return 'Local service is not running. Run scripts/start-realtime-audio-capture.sh first.';
  }

  return message;
}

function isUnsupportedActionError(message) {
  return message.includes('Unsupported OPAS extension action');
}

async function sendExtensionMessageWithFallback(actions, payload) {
  let lastError = null;

  for (const action of actions) {
    try {
      return await sendExtensionMessage(action, payload);
    } catch (error) {
      lastError = error;
      if (!isUnsupportedActionError(error.message)) {
        throw error;
      }
    }
  }

  throw lastError || new Error('Extension request failed.');
}

function sendExtensionMessage(action, payload) {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage({ action, payload }, (response) => {
      const error = chrome.runtime.lastError;
      if (error) {
        reject(new Error(error.message));
        return;
      }

      if (!response?.ok) {
        reject(new Error(response?.error || 'Extension request failed.'));
        return;
      }

      resolve(response);
    });
  });
}
