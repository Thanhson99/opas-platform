import httpClient from '../../../api/httpClient';

/**
 * Load the stock list.
 *
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function getStocks() {
    const response = await httpClient.get('/stocks');

    return response.data.data ?? [];
}

/**
 * Add a stock to the current user's favorites.
 *
 * @param {string} symbol
 * @returns {Promise<void>}
 */
export async function addFavoriteStock(symbol) {
    await httpClient.put(`/stocks/favorites/${symbol}`);
}

/**
 * Remove a stock from the current user's favorites.
 *
 * @param {string} symbol
 * @returns {Promise<void>}
 */
export async function removeFavoriteStock(symbol) {
    await httpClient.delete(`/stocks/favorites/${symbol}`);
}
