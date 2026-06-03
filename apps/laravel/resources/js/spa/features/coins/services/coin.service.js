import httpClient from '../../../api/httpClient';

/**
 * Load the market list from the coin API.
 *
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function getCoins() {
    const response = await httpClient.get('/coins');

    return response.data.data ?? [];
}

/**
 * Load one monitored coin symbol.
 *
 * @param {string} symbol
 * @returns {Promise<Record<string, unknown>|null>}
 */
export async function getCoin(symbol) {
    const response = await httpClient.get(`/coins/${symbol}`);

    return response.data.data ?? null;
}

/**
 * Add a coin to the current user's favorites.
 *
 * @param {string} symbol
 * @returns {Promise<void>}
 */
export async function addFavoriteCoin(symbol) {
    await httpClient.put(`/coins/favorites/${symbol}`);
}

/**
 * Remove a coin from the current user's favorites.
 *
 * @param {string} symbol
 * @returns {Promise<void>}
 */
export async function removeFavoriteCoin(symbol) {
    await httpClient.delete(`/coins/favorites/${symbol}`);
}

/**
 * Load keyword automation inputs for coin content.
 *
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function getCoinKeywords() {
    const response = await httpClient.get('/coins/keywords');

    return response.data.data ?? [];
}

/**
 * Create a keyword automation input.
 *
 * @param {{ keyword: string, tags: Array<string> }} payload
 * @returns {Promise<void>}
 */
export async function createCoinKeyword(payload) {
    await httpClient.post('/coins/keywords', payload);
}

/**
 * Delete a keyword automation input.
 *
 * @param {number|string} id
 * @returns {Promise<void>}
 */
export async function deleteCoinKeyword(id) {
    await httpClient.delete(`/coins/keywords/${id}`);
}

/**
 * Load configured coin price alerts.
 *
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function getCoinAlerts() {
    const response = await httpClient.get('/coins/alerts');

    return response.data.data ?? [];
}

/**
 * Toggle one coin price-alert status.
 *
 * @param {number|string} id
 * @returns {Promise<void>}
 */
export async function toggleCoinAlert(id) {
    await httpClient.patch(`/coins/alerts/${id}/toggle`);
}

/**
 * Load one configured coin price alert.
 *
 * @param {number|string} id
 * @returns {Promise<Record<string, unknown>>}
 */
export async function getCoinAlert(id) {
    const response = await httpClient.get(`/coins/alerts/${id}`);

    return response.data.data;
}

/**
 * Update one configured coin price alert.
 *
 * @param {number|string} id
 * @param {{ threshold_percent: number|string|null, type: string, direction: string|null, is_active: boolean }} payload
 * @returns {Promise<void>}
 */
export async function updateCoinAlert(id, payload) {
    await httpClient.put(`/coins/alerts/${id}`, payload);
}
