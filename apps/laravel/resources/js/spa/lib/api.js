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
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

if (csrf) {
    api.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}

export default api;
