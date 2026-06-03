import { normalizeApiError } from './errors';

const BASE_URL = '/api';
const JSON_CONTENT_TYPE = 'application/json';
const DEFAULT_REQUEST_TIMEOUT_MS = 15000;

/**
 * Read one cookie value by name.
 *
 * @param {string} name
 * @returns {string}
 */
function readCookie(name) {
    if (typeof document === 'undefined') {
        return '';
    }

    const prefix = `${name}=`;
    const cookie = document.cookie.split('; ').find((candidate) => candidate.startsWith(prefix));

    return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : '';
}

/**
 * Build an API URL with optional query parameters.
 *
 * @param {string} path
 * @param {Record<string, unknown> | undefined} params
 * @returns {string}
 */
function buildUrl(path, params) {
    const url = new URL(`${BASE_URL}${path}`, window.location.origin);

    Object.entries(params ?? {}).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
            return;
        }

        url.searchParams.set(key, String(value));
    });

    return `${url.pathname}${url.search}`;
}

/**
 * Parse a JSON response when the server returned a JSON payload.
 *
 * @param {Response} response
 * @returns {Promise<unknown>}
 */
async function parseResponseData(response) {
    if (response.status === 204) {
        return null;
    }

    const contentType = response.headers.get('content-type') ?? '';

    if (!contentType.includes(JSON_CONTENT_TYPE)) {
        return response.text();
    }

    return response.json();
}

/**
 * Send one API request and return an Axios-compatible response envelope.
 *
 * @param {string} method
 * @param {string} path
 * @param {Record<string, unknown> | undefined} payload
 * @param {{ params?: Record<string, unknown>, timeoutMs?: number }} config
 * @returns {Promise<{ data: any, status: number, response: Response }>}
 */
async function request(method, path, payload, config = {}) {
    const abortController = new AbortController();
    const timeoutMs =
        Number.isFinite(config.timeoutMs) && config.timeoutMs > 0
            ? config.timeoutMs
            : DEFAULT_REQUEST_TIMEOUT_MS;
    const timeoutHandle = window.setTimeout(() => abortController.abort(), timeoutMs);
    const headers = {
        Accept: JSON_CONTENT_TYPE,
        'X-Requested-With': 'XMLHttpRequest',
    };
    const csrfToken = readCookie('XSRF-TOKEN');
    const options = {
        method,
        credentials: 'same-origin',
        headers,
        signal: abortController.signal,
    };

    if (csrfToken) {
        headers['X-XSRF-TOKEN'] = csrfToken;
    }

    if (payload !== undefined) {
        headers['Content-Type'] = JSON_CONTENT_TYPE;
        options.body = JSON.stringify(payload);
    }

    try {
        const response = await fetch(buildUrl(path, config.params), options);
        const data = await parseResponseData(response);
        const envelope = { data, status: response.status, response };

        if (!response.ok) {
            throw envelope;
        }

        return envelope;
    } catch (error) {
        throw normalizeApiError(error);
    } finally {
        window.clearTimeout(timeoutHandle);
    }
}

const httpClient = {
    get: (path, config) => request('GET', path, undefined, config),
    post: (path, payload, config) => request('POST', path, payload, config),
    put: (path, payload, config) => request('PUT', path, payload, config),
    patch: (path, payload, config) => request('PATCH', path, payload, config),
    delete: (path, config) => request('DELETE', path, undefined, config),
};

export default httpClient;
