import httpClient from '../../../api/httpClient';

const LONG_WORKFLOW_TIMEOUT_MS = 600000;

/**
 * Load active Douyin keywords.
 *
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function getDouyinKeywords() {
    const response = await httpClient.get('/douyin/keywords');

    return response.data.data ?? [];
}

/**
 * Create one Douyin keyword.
 *
 * @param {{ name: string, category?: string }} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function createDouyinKeyword(payload) {
    const response = await httpClient.post('/douyin/keywords', payload);

    return response.data.data;
}

/**
 * Start a crawl preview request.
 *
 * @param {{ keyword: string, limit: number }} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function crawlDouyinPreview(payload) {
    const response = await httpClient.post('/douyin/crawl', payload, {
        timeoutMs: LONG_WORKFLOW_TIMEOUT_MS,
    });

    return response.data.data;
}

/**
 * Toggle a preview video's selected state.
 *
 * @param {number|string} id
 * @param {boolean} selected
 * @returns {Promise<Record<string, unknown>>}
 */
export async function updateDouyinVideoSelection(id, selected) {
    const response = await httpClient.patch(`/douyin/videos/${id}/selection`, { selected });

    return response.data.data;
}

/**
 * Process selected videos for one crawl job.
 *
 * @param {number|string} jobId
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function processDouyinSelected(jobId) {
    const response = await httpClient.post(
        `/douyin/jobs/${jobId}/process-selected`,
        {},
        { timeoutMs: LONG_WORKFLOW_TIMEOUT_MS },
    );

    return response.data.data ?? [];
}

/**
 * Load stored Douyin videos by status.
 *
 * @param {{ status?: string, keyword?: string, page?: number }} filters
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function getDouyinVideos(filters = {}) {
    const response = await httpClient.get('/douyin/videos', { params: filters });

    return response.data.data ?? [];
}

/**
 * Mark one video posted.
 *
 * @param {number|string} id
 * @param {boolean} deleteAfterPosted
 * @returns {Promise<Record<string, unknown>>}
 */
export async function markDouyinVideoPosted(id, deleteAfterPosted = false) {
    const response = await httpClient.post(`/douyin/videos/${id}/mark-posted`, {
        delete_after_posted: deleteAfterPosted,
    });

    return response.data.data;
}

/**
 * Delete one Douyin video row and local files.
 *
 * @param {number|string} id
 * @returns {Promise<void>}
 */
export async function deleteDouyinVideo(id) {
    await httpClient.delete(`/douyin/videos/${id}`);
}
