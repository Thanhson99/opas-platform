import { getProviderActionText } from '../lib/publicAuthProviders';

/**
 * Show a safe fallback notice when the frontend cannot render a provider interaction yet.
 */
export default function UnsupportedAuthProviderNotice({ provider, action, t }) {
    return (
        <div className="app-provider-note" role="status">
            {getProviderActionText(provider, action, t)}. {t('auth.providerUiUnavailableSuffix')}
        </div>
    );
}
