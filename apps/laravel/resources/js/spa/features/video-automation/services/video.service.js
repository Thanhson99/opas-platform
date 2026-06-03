import httpClient from '../../../api/httpClient';

/**
 * Load grouped trending video sources.
 *
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function getTrendingVideos() {
    const response = await httpClient.get('/videos/trending');

    return response.data.data ?? [];
}
