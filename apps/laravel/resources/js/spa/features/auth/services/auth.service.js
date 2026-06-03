import httpClient from '../../../api/httpClient';

/**
 * Request a password reset link for an email address.
 *
 * @param {{ email: string }} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function requestPasswordReset(payload) {
    const response = await httpClient.post('/auth/forgot-password', payload);

    return response.data;
}

/**
 * Complete a password reset request.
 *
 * @param {{ email: string, password: string, password_confirmation: string, token: string }} payload
 * @returns {Promise<void>}
 */
export async function resetPassword(payload) {
    await httpClient.post('/auth/reset-password', payload);
}

/**
 * Verify an email address with a six-digit code.
 *
 * @param {{ email: string, code: string }} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function verifyEmail(payload) {
    const response = await httpClient.post('/auth/email/verify', payload);

    return response.data;
}

/**
 * Request a new email verification code.
 *
 * @param {{ email: string }} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function resendEmailVerification(payload) {
    const response = await httpClient.post('/auth/email/verification-notification', payload);

    return response.data;
}

/**
 * Update the authenticated account profile.
 *
 * @param {{ name: string }} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function updateAccountProfile(payload) {
    const response = await httpClient.put('/auth/account', payload);

    return response.data;
}

/**
 * Unlink one provider from the authenticated account.
 *
 * @param {string} providerKey
 * @returns {Promise<Record<string, unknown>>}
 */
export async function unlinkAccountProvider(providerKey) {
    const response = await httpClient.delete(`/auth/account/providers/${providerKey}`);

    return response.data;
}

/**
 * Load admin-managed authentication providers.
 *
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function getAdminAuthProviders() {
    const response = await httpClient.get('/admin/auth/providers');

    return response.data.data ?? [];
}

/**
 * Update one admin-managed authentication provider.
 *
 * @param {string} providerKey
 * @param {Record<string, unknown>} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function updateAdminAuthProvider(providerKey, payload) {
    const response = await httpClient.put(`/admin/auth/providers/${providerKey}`, payload);

    return response.data.data;
}

/**
 * Load admin-managed users.
 *
 * @param {{ page: number, perPage: number, search: string }} options
 * @returns {Promise<Record<string, unknown>>}
 */
export async function getAdminUsers({ page, perPage, search }) {
    const response = await httpClient.get('/admin/users', {
        params: {
            page,
            per_page: perPage,
            search: search || undefined,
        },
    });

    return response.data;
}

/**
 * Update one admin-managed user.
 *
 * @param {string} userId
 * @param {{ name: string, role: string }} payload
 * @returns {Promise<Record<string, unknown>>}
 */
export async function updateAdminUser(userId, payload) {
    const response = await httpClient.put(`/admin/users/${userId}`, payload);

    return response.data;
}

/**
 * Delete one admin-managed user.
 *
 * @param {string} userId
 * @returns {Promise<void>}
 */
export async function deleteAdminUser(userId) {
    await httpClient.delete(`/admin/users/${userId}`);
}

/**
 * Reset one admin-managed user's password.
 *
 * @param {string} userId
 * @returns {Promise<Record<string, unknown>>}
 */
export async function resetAdminUserPassword(userId) {
    const response = await httpClient.post(`/admin/users/${userId}/reset-password`);

    return response.data;
}
