const DEFAULT_BACKEND_URL = 'http://127.0.0.1:5010/realtime-audio';
const DEFAULT_LARAVEL_URL = `${window.location.origin}/realtime-translate`;
const EXTENSION_SOURCE = 'opas-realtime-audio-extension';
const UI_SOURCE = 'opas-realtime-audio-ui';
const REQUEST_TIMEOUT_MS = 5000;

/**
 * Build the local realtime service client.
 *
 * @param {string} backendUrl
 * @returns {{
 *   backendUrl: string,
 *   websocketUrl: (sessionId: string) => string,
 *   health: () => Promise<Record<string, unknown>>,
 *   sttConfig: () => Promise<Record<string, unknown>>,
 *   warmup: () => Promise<Record<string, unknown>>,
 *   configureStt: (payload: Record<string, unknown>) => Promise<Record<string, unknown>>,
 *   transcript: (sessionId: string) => Promise<Record<string, unknown>>,
 * }}
 */
export function createRealtimeServiceClient(backendUrl = DEFAULT_BACKEND_URL) {
    const normalizedBackendUrl = normalizeBackendUrl(backendUrl);

    return {
        backendUrl: normalizedBackendUrl,
        websocketUrl: (sessionId) =>
            `${normalizedBackendUrl.replace(/^http/, 'ws')}/sessions/${sessionId}/transcript/ws`,
        health: () => requestJson(`${normalizedBackendUrl}/health`),
        sttConfig: () => requestJson(`${normalizedBackendUrl}/stt/config`),
        warmup: () => requestJson(`${normalizedBackendUrl}/stt/warmup`, { method: 'POST' }),
        configureStt: (payload) =>
            requestJson(`${normalizedBackendUrl}/stt/config`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            }),
        transcript: (sessionId) =>
            requestJson(`${normalizedBackendUrl}/sessions/${sessionId}/transcript`),
    };
}

/**
 * Request one action from the Chrome extension content bridge.
 *
 * @param {string} action
 * @param {Record<string, unknown>} payload
 * @param {number} timeoutMs
 * @returns {Promise<Record<string, unknown>>}
 */
export function requestExtension(action, payload = {}, timeoutMs = REQUEST_TIMEOUT_MS) {
    return new Promise((resolve, reject) => {
        const requestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const timeout = window.setTimeout(() => {
            window.removeEventListener('message', handleMessage);
            reject(new Error('Extension bridge did not respond.'));
        }, timeoutMs);

        function handleMessage(event) {
            if (
                event.source !== window ||
                event.data?.source !== EXTENSION_SOURCE ||
                event.data?.type !== 'RESPONSE' ||
                event.data?.requestId !== requestId
            ) {
                return;
            }

            window.clearTimeout(timeout);
            window.removeEventListener('message', handleMessage);

            if (!event.data.ok) {
                reject(new Error(event.data.error || 'Extension request failed.'));
                return;
            }

            resolve(event.data.response || {});
        }

        window.addEventListener('message', handleMessage);
        window.postMessage(
            {
                source: UI_SOURCE,
                type: 'REQUEST',
                requestId,
                action,
                payload,
            },
            '*',
        );
    });
}

/**
 * Open the best-effort Chrome extension management screen.
 *
 * @returns {void}
 */
export function openExtensionSetupScreen() {
    window.open('chrome://extensions/', '_blank', 'noopener,noreferrer');
}

/**
 * Return the Laravel URL the extension popup should open.
 *
 * @returns {string}
 */
export function realtimeTranslateUrl() {
    return DEFAULT_LARAVEL_URL;
}

/**
 * Normalize local backend URLs.
 *
 * @param {string} value
 * @returns {string}
 */
export function normalizeBackendUrl(value) {
    return String(value || DEFAULT_BACKEND_URL).replace(/\/+$/, '');
}

/**
 * Resolve one user-facing error message from a thrown value.
 *
 * @param {unknown} error
 * @returns {string}
 */
export function errorMessage(error) {
    return error instanceof Error ? error.message : String(error || 'Unknown error');
}

/**
 * Wait for a small realtime workflow delay.
 *
 * @param {number} ms
 * @returns {Promise<void>}
 */
export function delay(ms) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

/**
 * Fetch and parse a JSON local-service response.
 *
 * @param {string} url
 * @param {RequestInit} options
 * @returns {Promise<Record<string, unknown>>}
 */
async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', ...(options.headers || {}) },
        ...options,
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}
