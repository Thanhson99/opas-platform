/**
 * Prefer server-side validation messages and only reveal client-side issues after touch.
 */
export function getFieldError(fieldIssues, serverErrors, fieldTouches, key) {
    if (serverErrors[key]?.[0]) {
        return serverErrors[key][0];
    }

    if (!fieldTouches[key]) {
        return '';
    }

    return fieldIssues[key] || '';
}

/**
 * Build stable input names for provider settings fields and secret inputs.
 */
export function buildProviderInputName(providerKey, fieldKey, bucket = 'base') {
    return `auth-provider-${providerKey}-${bucket}-${fieldKey}`;
}
