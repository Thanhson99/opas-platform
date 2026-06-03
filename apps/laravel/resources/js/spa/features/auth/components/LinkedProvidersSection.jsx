import { Link } from 'react-router-dom';
import AppIcon from '../../../components/icons/AppIcon';
import { getRedirectUrl } from '../lib/publicAuthProviders';

/**
 * Render linked and linkable account providers.
 *
 * @param {{
 *   currentSignInProvider?: Record<string, unknown>,
 *   linkableProviders: Array<Record<string, unknown>>,
 *   linkedProviders: Array<Record<string, unknown>>,
 *   providerError: string,
 *   t: (key: string) => string,
 *   unlinkingProviderKey: string,
 *   onConfirmProvider: (provider: Record<string, unknown>) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function LinkedProvidersSection({
    currentSignInProvider,
    linkableProviders,
    linkedProviders,
    providerError,
    t,
    unlinkingProviderKey,
    onConfirmProvider,
}) {
    return (
        <section
            id="linked-providers"
            className="app-form-card app-account-settings app-account-settings__providers-card"
        >
            <LinkedProvidersHeader providerError={providerError} t={t} />
            <div className="app-account-settings__provider-grid">
                {linkedProviders.length > 0 ? (
                    linkedProviders.map((provider) => (
                        <LinkedProviderCard
                            currentSignInProvider={currentSignInProvider}
                            key={provider.key}
                            provider={provider}
                            t={t}
                            unlinkingProviderKey={unlinkingProviderKey}
                            onConfirmProvider={onConfirmProvider}
                        />
                    ))
                ) : (
                    <p className="app-field__hint">{t('accountSettings.noLinkedProviders')}</p>
                )}
            </div>

            {linkableProviders.length > 0 ? (
                <LinkableProvidersPanel linkableProviders={linkableProviders} t={t} />
            ) : null}

            <div className="app-account-settings__help-row">
                <p className="app-field__hint">{t('accountSettings.passwordHelp')}</p>
                <Link
                    to="/forgot-password"
                    className="app-button app-button--ghost"
                    title={t('accountSettings.resetPassword')}
                >
                    <AppIcon name="lock" />
                    {t('accountSettings.resetPassword')}
                </Link>
            </div>
        </section>
    );
}

function LinkedProvidersHeader({ providerError, t }) {
    return (
        <div className="app-account-settings__section-head">
            <div>
                <h3 className="app-form-card__title">
                    {t('accountSettings.linkedProvidersTitle')}
                </h3>
                <p className="app-form-card__text">{t('accountSettings.linkedProvidersText')}</p>
            </div>
            {providerError ? <p className="app-field__error">{providerError}</p> : null}
        </div>
    );
}

function LinkedProviderCard({
    currentSignInProvider,
    provider,
    t,
    unlinkingProviderKey,
    onConfirmProvider,
}) {
    const isCurrentProvider = currentSignInProvider?.key === provider.key;

    return (
        <article className="app-account-settings__provider-card">
            <div className="app-account-settings__provider-head">
                <span className="app-account-settings__provider-icon">
                    <AppIcon name={provider.icon ?? provider.key} />
                </span>
                <div className="app-account-settings__provider-copy">
                    <strong>{provider.display_name}</strong>
                    <span>
                        {isCurrentProvider
                            ? t('accountSettings.currentProviderLocked')
                            : t('accountSettings.linkedReady')}
                    </span>
                </div>
            </div>
            <div className="app-account-settings__provider-actions">
                <button
                    type="button"
                    className="app-button app-button--ghost app-account-settings__provider-button"
                    disabled={isCurrentProvider}
                    onClick={() => onConfirmProvider(provider)}
                    title={`${t('accountSettings.unlink')} ${provider.display_name}`}
                >
                    <AppIcon name="link" />
                    {unlinkingProviderKey === provider.key
                        ? t('accountSettings.unlinking')
                        : t('accountSettings.unlink')}
                </button>
            </div>
        </article>
    );
}

function LinkableProvidersPanel({ linkableProviders, t }) {
    return (
        <div className="app-account-settings__link-panel">
            <div>
                <h4 className="app-account-settings__subheading">
                    {t('accountSettings.availableProvidersTitle')}
                </h4>
                <p className="app-form-card__text">{t('accountSettings.availableProvidersText')}</p>
            </div>
            <div className="app-account-settings__link-actions">
                {linkableProviders.map((provider) => (
                    <a
                        key={provider.key}
                        href={getRedirectUrl(provider)}
                        className="app-account-settings__link-action"
                        title={`${t('accountSettings.connectWith')} ${provider.display_name}`}
                    >
                        <AppIcon name={provider.icon ?? provider.key} />
                        <span>
                            {t('accountSettings.connectWith')} {provider.display_name}
                        </span>
                    </a>
                ))}
            </div>
        </div>
    );
}
