import axios from 'axios';

/**
 * Expose the default Axios client for legacy scripts that still rely on window globals.
 */
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
