/**
 * @typedef {{
 *   key: string,
 *   display_name?: string,
 *   type?: string,
 *   icon?: string | null,
 *   capabilities?: Record<string, boolean>,
 *   metadata?: Record<string, unknown>,
 * }} PublicAuthProvider
 */

/**
 * @typedef {{
 *   key?: string,
 * }} LinkedProviderRef
 */

/**
 * @typedef {{
 *   linked_providers?: LinkedProviderRef[],
 * }} LinkedProviderOwner
 */

/**
 * Check whether a provider exposes one frontend capability flag.
 *
 * @param {PublicAuthProvider | null | undefined} provider
 * @param {string} capability
 * @returns {boolean}
 */
export function hasProviderCapability(provider, capability) {
    return Boolean(provider?.capabilities?.[capability]);
}

/**
 * Filter one provider list down to entries that support the requested capability.
 *
 * @param {PublicAuthProvider[] | null | undefined} providers
 * @param {string} capability
 * @returns {PublicAuthProvider[]}
 */
export function getProvidersForCapability(providers, capability) {
    if (!Array.isArray(providers)) {
        return [];
    }

    return providers.filter((provider) => hasProviderCapability(provider, capability));
}

/**
 * Extract the linked provider keys already attached to the current account.
 *
 * @param {LinkedProviderOwner | null | undefined} user
 * @returns {string[]}
 */
export function getLinkedProviderKeys(user) {
    if (!Array.isArray(user?.linked_providers)) {
        return [];
    }

    return user.linked_providers
        .map((provider) => provider?.key)
        .filter((key) => typeof key === 'string' && key.trim() !== '');
}

/**
 * Distinguish the built-in password flow from redirect-based providers.
 *
 * @param {PublicAuthProvider | null | undefined} provider
 * @returns {boolean}
 */
export function isPasswordProvider(provider) {
    return provider?.type === 'password' || provider?.capabilities?.supports_password === true;
}

/**
 * Identify providers that leave the SPA for OAuth-style sign-in.
 *
 * @param {PublicAuthProvider | null | undefined} provider
 * @returns {boolean}
 */
export function isRedirectProvider(provider) {
    return provider?.capabilities?.requires_redirect === true || provider?.type === 'oauth2';
}

/**
 * Resolve the email/password provider used to render the inline auth form.
 *
 * @param {PublicAuthProvider[]} providers
 * @returns {PublicAuthProvider | null}
 */
export function getPasswordFormProvider(providers) {
    return (
        providers.find((provider) => provider.key === 'email' && isPasswordProvider(provider)) ??
        null
    );
}

/**
 * Return providers that should render as secondary actions outside the password form.
 *
 * @param {PublicAuthProvider[]} providers
 * @param {PublicAuthProvider | null} formProvider
 * @returns {PublicAuthProvider[]}
 */
export function getNonFormProviders(providers, formProvider) {
    return providers.filter((provider) => provider.key !== formProvider?.key);
}

/**
 * Return redirect providers that can still be linked to the current account.
 *
 * @param {PublicAuthProvider[]} providers
 * @param {LinkedProviderOwner | null | undefined} user
 * @returns {PublicAuthProvider[]}
 */
export function getLinkableProviders(providers, user) {
    const linkedProviderKeys = new Set(getLinkedProviderKeys(user));

    return getProvidersForCapability(providers, 'link_account').filter(
        (provider) => isRedirectProvider(provider) && !linkedProviderKeys.has(provider.key),
    );
}

/**
 * Resolve the backend redirect endpoint for one public provider button.
 *
 * @param {PublicAuthProvider} provider
 * @returns {string}
 */
export function getRedirectUrl(provider) {
    return provider?.metadata?.redirect_url ?? `/api/auth/providers/${provider.key}/redirect`;
}

/**
 * Treat blank custom button labels as absent so provider-aware fallbacks remain usable.
 *
 * @param {unknown} value
 * @returns {string | null}
 */
function resolveConfiguredActionText(value) {
    if (typeof value !== 'string') {
        return null;
    }

    return value.trim() === '' ? null : value;
}

/**
 * Build the provider action label shown on login and registration buttons.
 *
 * @param {PublicAuthProvider} provider
 * @param {'login' | 'register'} action
 * @param {(key: string) => string} t
 * @returns {string}
 */
export function getProviderActionText(provider, action, t) {
    const configuredRegisterText = resolveConfiguredActionText(
        provider?.metadata?.register_button_text,
    );

    if (action === 'register' && configuredRegisterText !== null) {
        return configuredRegisterText;
    }

    const configuredLoginText = resolveConfiguredActionText(provider?.metadata?.button_text);

    if (action === 'login' && configuredLoginText !== null) {
        return configuredLoginText;
    }

    const prefix =
        action === 'register' ? t('auth.registerWithProvider') : t('auth.continueWithProvider');

    return `${prefix} ${provider.display_name}`;
}
