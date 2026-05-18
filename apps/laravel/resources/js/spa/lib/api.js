import axios from 'axios';

/**
 * Shared Axios instance for the React SPA.
 *
 * All frontend data access should go through this client so headers,
 * base URL, and future interceptors stay centralized.
 */
const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

export default api;
