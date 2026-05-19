import { getProviderActionText } from '../lib/publicAuthProviders';

export default function UnsupportedAuthProviderNotice({ provider, action, t }) {
    return (
        <div className="app-provider-note" role="status">
            {getProviderActionText(provider, action, t)}. {t('auth.providerUiUnavailableSuffix')}
        </div>
    );
}
