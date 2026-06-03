export const ADMIN_USERS_PER_PAGE = 10;

export const EMPTY_ADMIN_USERS_PAGINATION = {
    currentPage: 1,
    lastPage: 1,
    perPage: ADMIN_USERS_PER_PAGE,
    total: 0,
    from: 0,
    to: 0,
};

/**
 * Build editable admin-form rows keyed by user id.
 *
 * @param {Array<Record<string, unknown>>} users
 * @returns {Record<string, { name: string, email: string, role: string }>}
 */
export function buildAdminUserForms(users) {
    return Object.fromEntries(
        users.map((user) => [
            String(user.id),
            {
                name: user.name ?? '',
                email: user.email ?? '',
                role: user.role ?? 'member',
            },
        ]),
    );
}

/**
 * Collapse backend validation payloads into the first useful admin-user message.
 *
 * @param {unknown} requestError
 * @param {string} fallbackMessage
 * @returns {string}
 */
export function firstAdminUserErrorMessage(requestError, fallbackMessage) {
    const errors = requestError?.response?.data?.errors;

    if (errors && typeof errors === 'object') {
        const firstField = Object.values(errors)[0];

        if (Array.isArray(firstField) && firstField[0]) {
            return firstField[0];
        }
    }

    return requestError?.response?.data?.message || fallbackMessage;
}

/**
 * Detect whether one editable admin-user row diverged from its initial state.
 *
 * @param {{ name: string, role: string }|undefined} form
 * @param {{ name: string, role: string }|undefined} initialForm
 * @returns {boolean}
 */
export function hasAdminUserRowChanged(form, initialForm) {
    if (!form || !initialForm) {
        return false;
    }

    return form.name.trim() !== initialForm.name.trim() || form.role !== initialForm.role;
}

/**
 * Check whether one admin-user row contains the minimum values required to save.
 *
 * @param {{ name?: string, role?: string }|undefined} form
 * @returns {boolean}
 */
export function isAdminUserRowSubmittable(form) {
    return Boolean(form?.name?.trim()) && Boolean(form?.role);
}

/**
 * Build a compact pagination model centered around the current page.
 *
 * @param {number} currentPage
 * @param {number} lastPage
 * @returns {Array<number>}
 */
export function buildAdminUserPaginationItems(currentPage, lastPage) {
    if (lastPage <= 1) {
        return [1];
    }

    const pages = new Set([1, lastPage, currentPage, currentPage - 1, currentPage + 1]);

    return Array.from(pages)
        .filter((page) => page >= 1 && page <= lastPage)
        .sort((left, right) => left - right);
}
