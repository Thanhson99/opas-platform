import RedirectAuthProviderButton from '../components/RedirectAuthProviderButton';
import UnsupportedAuthProviderNotice from '../components/UnsupportedAuthProviderNotice';
import { isRedirectProvider } from './publicAuthProviders';

function renderRedirectProvider(provider, action, t) {
    return (
        <RedirectAuthProviderButton key={provider.key} provider={provider} action={action} t={t} />
    );
}

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
