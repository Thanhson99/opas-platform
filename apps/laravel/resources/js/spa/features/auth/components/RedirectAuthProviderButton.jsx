import AppIcon, { hasAppIcon } from '../../../components/icons/AppIcon';
import { getProviderActionText, getRedirectUrl } from '../lib/publicAuthProviders';

function buildOauthButtonClass(key) {
    return `app-button app-social-button app-social-button--${key}`;
}

export default function RedirectAuthProviderButton({ provider, action, t }) {
    const actionText = getProviderActionText(provider, action, t);

    return (
        <a
            className={buildOauthButtonClass(provider.key)}
            href={getRedirectUrl(provider)}
            aria-label={actionText}
        >
            {hasAppIcon(provider.icon) ? (
                <span
                    className={`app-social-button__icon app-social-button__icon--${provider.key}`}
                >
                    <AppIcon name={provider.icon} />
                </span>
            ) : null}
            <span className="app-social-button__label">{actionText}</span>
        </a>
    );
}
