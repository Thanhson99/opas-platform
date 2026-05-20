import RedirectAuthProviderButton from '../components/RedirectAuthProviderButton';
import UnsupportedAuthProviderNotice from '../components/UnsupportedAuthProviderNotice';
import { isRedirectProvider } from './publicAuthProviders';

/**
 * Render one redirect-capable provider with the shared OAuth button component.
 */
function renderRedirectProvider(provider, action, t) {
    return (
        <RedirectAuthProviderButton key={provider.key} provider={provider} action={action} t={t} />
    );
}

/**
 * Render a safe placeholder when the provider type is not yet supported by the SPA.
 */
function renderUnsupportedProvider(provider, action, t) {
    return (
        <UnsupportedAuthProviderNotice
            key={provider.key}
            provider={provider}
            action={action}
            t={t}
        />
    );
}

const providerRenderers = Object.freeze({
    oauth2: renderRedirectProvider,
});

/**
 * Resolve the correct public auth-provider renderer for the current provider contract.
 */
export function renderPublicAuthProvider(provider, action, t) {
    const renderer = providerRenderers[provider.type];

    if (typeof renderer === 'function') {
        return renderer(provider, action, t);
    }

    if (isRedirectProvider(provider)) {
        return renderRedirectProvider(provider, action, t);
    }

    return renderUnsupportedProvider(provider, action, t);
}
