/**
 * Normalize API and network failures into a stable frontend error contract.
 *
 * @param {unknown} error
 * @returns {{ status: number | null, message: string, errors: Record<string, string[]>, response?: { status: number | null, data: any } }}
 */
export function normalizeApiError(error) {
    const response = error?.response ?? error;
    const data = response?.data;
    const status = response?.status ?? null;

    return {
        status,
        message:
            typeof data?.message === 'string' && data.message.trim()
                ? data.message
                : 'Request failed',
        errors: data?.errors && typeof data.errors === 'object' ? data.errors : {},
        response: {
            status,
            data,
        },
    };
}
