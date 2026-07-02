if (!window.__opasRealtimeAudioBridgeInstalled) {
  window.__opasRealtimeAudioBridgeInstalled = true;

  window.addEventListener('message', (event) => {
    if (event.source !== window) {
      return;
    }

    const message = event.data;
    if (message?.source === 'opas-realtime-audio-ui' && message.type === 'PING') {
      window.postMessage(
        {
          source: 'opas-realtime-audio-extension',
          type: 'PONG',
          requestId: message.requestId,
        },
        '*',
      );
      return;
    }

    if (message?.source !== 'opas-realtime-audio-ui' || message.type !== 'REQUEST') {
      return;
    }

    chrome.runtime.sendMessage(
      {
        action: message.action,
        payload: message.payload || {},
      },
      (response) => {
        const error = chrome.runtime.lastError;
        window.postMessage(
          {
            source: 'opas-realtime-audio-extension',
            type: 'RESPONSE',
            requestId: message.requestId,
            ok: !error && response?.ok !== false,
            response: response || null,
            error: error?.message || response?.error || null,
          },
          '*',
        );
      },
    );
  });

  window.postMessage(
    {
      source: 'opas-realtime-audio-extension',
      type: 'READY',
    },
    '*',
  );
}
