export function hasProviderCapability(provider, capability) {
    return Boolean(provider?.capabilities?.[capability]);
}

export function getProvidersForCapability(providers, capability) {
    if (!Array.isArray(providers)) {
        return [];
    }

    return providers.filter((provider) => hasProviderCapability(provider, capability));
}

export function getLinkedProviderKeys(user) {
    if (!Array.isArray(user?.linked_providers)) {
        return [];
    }

    return user.linked_providers
        .map((provider) => provider?.key)
        .filter((key) => typeof key === 'string' && key.trim() !== '');
}

export function isPasswordProvider(provider) {
    return provider?.type === 'password' || provider?.capabilities?.supports_password === true;
}

export function isRedirectProvider(provider) {
    return provider?.capabilities?.requires_redirect === true || provider?.type === 'oauth2';
}

export function getPasswordFormProvider(providers) {
    return (
        providers.find((provider) => provider.key === 'email' && isPasswordProvider(provider)) ??
        null
    );
}

export function getNonFormProviders(providers, formProvider) {
    return providers.filter((provider) => provider.key !== formProvider?.key);
}

export function getLinkableProviders(providers, user) {
    const linkedProviderKeys = new Set(getLinkedProviderKeys(user));

    return getProvidersForCapability(providers, 'link_account').filter(
        (provider) => isRedirectProvider(provider) && !linkedProviderKeys.has(provider.key),
    );
}

export function getRedirectUrl(provider) {
    return provider?.metadata?.redirect_url ?? `/api/auth/providers/${provider.key}/redirect`;
}

export function getProviderActionText(provider, action, t) {
    if (action === 'register' && typeof provider?.metadata?.register_button_text === 'string') {
        return provider.metadata.register_button_text;
    }

    if (action === 'login' && typeof provider?.metadata?.button_text === 'string') {
        return provider.metadata.button_text;
    }

    const prefix =
        action === 'register' ? t('auth.registerWithProvider') : t('auth.continueWithProvider');

    return `${prefix} ${provider.display_name}`;
}
