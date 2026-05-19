import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import AppIcon from '../../../components/icons/AppIcon';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import LoadingState from '../../../components/ui/LoadingState';
import PageHero from '../../../components/ui/PageHero';
import api from '../../../lib/api';
import { useAuth } from '../context/AuthContext';
import { getRedirectUrl } from '../lib/publicAuthProviders';
import { useLanguage } from '../../i18n/context/LanguageContext';

function firstErrorMessage(requestError, fallbackMessage) {
    const errors = requestError?.response?.data?.errors;

    if (errors && typeof errors === 'object') {
        const firstField = Object.values(errors)[0];

        if (Array.isArray(firstField) && firstField[0]) {
            return firstField[0];
        }
    }

    return requestError?.response?.data?.message || fallbackMessage;
}

export default function AccountSettingsPage() {
    const navigate = useNavigate();
    const { t } = useLanguage();
    const {
        user,
        loading,
        isAuthenticated,
        hasEmailLogin,
        linkableProviders,
        refreshUser,
        refreshAuthProviders,
    } = useAuth();
    const [name, setName] = useState('');
    const [formError, setFormError] = useState('');
    const [providerError, setProviderError] = useState('');
    const [flash, setFlash] = useState('');
    const [saving, setSaving] = useState(false);
    const [unlinkingProviderKey, setUnlinkingProviderKey] = useState('');
    const [confirmProvider, setConfirmProvider] = useState(null);

    useEffect(() => {
        if (!loading && !isAuthenticated) {
            navigate('/login', {
                replace: true,
                state: { from: { pathname: '/account' } },
            });
        }
    }, [isAuthenticated, loading, navigate]);

    useEffect(() => {
        setName(user?.name ?? '');
    }, [user?.name]);

    const linkedProviders = useMemo(
        () => (Array.isArray(user?.linked_providers) ? user.linked_providers : []),
        [user?.linked_providers],
    );

    if (loading) {
        return <LoadingState text={t('common.loadingAccount')} />;
    }

    if (!user) {
        return <LoadingState text={t('common.loadingAccount')} />;
    }

    const currentSignInProvider = user.current_sign_in_provider;
    const nameChanged = name.trim() !== (user.name ?? '').trim();

    const saveProfile = async (event) => {
        event.preventDefault();

        if (!name.trim() || !nameChanged) {
            return;
        }

        setSaving(true);
        setFormError('');
        setFlash('');

        try {
            const response = await api.put('/auth/account', {
                name: name.trim(),
            });

            setFlash(response.data.message || t('accountSettings.profileSaved'));
            await refreshUser();
        } catch (requestError) {
            setFormError(firstErrorMessage(requestError, t('accountSettings.saveError')));
        } finally {
            setSaving(false);
        }
    };

    const unlinkProvider = async () => {
        if (!confirmProvider) {
            return;
        }

        setUnlinkingProviderKey(confirmProvider.key);
        setProviderError('');
        setFlash('');

        try {
            const response = await api.delete(`/auth/account/providers/${confirmProvider.key}`);

            setFlash(response.data.message || t('accountSettings.unlinkSuccess'));
            setConfirmProvider(null);
            await Promise.all([refreshUser(), refreshAuthProviders()]);
        } catch (requestError) {
            setProviderError(firstErrorMessage(requestError, t('accountSettings.unlinkError')));
        } finally {
            setUnlinkingProviderKey('');
        }
    };

    return (
        <div className="app-shell app-account-settings-page">
            <PageHero
                eyebrow={t('accountSettings.eyebrow')}
                title={t('accountSettings.title')}
                text={t('accountSettings.text')}
                aside={
                    <div className="app-role-card">
                        <strong>{t('accountSettings.currentProvider')}</strong>
                        <span>
                            {currentSignInProvider?.display_name ??
                                t('accountSettings.unknownProvider')}
                        </span>
                    </div>
                }
            >
                <span className="app-status-pill app-status-pill--muted">
                    {t('accountSettings.sessionLabel')}:{' '}
                    {currentSignInProvider?.display_name ?? t('accountSettings.unknownProvider')}
                </span>
                {hasEmailLogin ? (
                    <span className="app-status-pill app-status-pill--success">
                        {t('accountSettings.emailFallbackEnabled')}
                    </span>
                ) : null}
            </PageHero>

            <section
                id="account-profile"
                className="app-form-card app-form-card--accent app-account-settings"
            >
                <div className="app-account-settings__section-head">
                    <div>
                        <h3 className="app-form-card__title">
                            {t('accountSettings.profileTitle')}
                        </h3>
                        <p className="app-form-card__text">{t('accountSettings.profileText')}</p>
                    </div>
                    {flash ? <p className="app-account-settings__flash">{flash}</p> : null}
                </div>

                <form className="app-form" onSubmit={saveProfile}>
                    <div className="app-form-grid app-account-settings__grid">
                        <div className="app-field">
                            <label className="app-label" htmlFor="account-name">
                                {t('auth.name')}
                            </label>
                            <input
                                id="account-name"
                                className="app-input"
                                value={name}
                                onChange={(event) => setName(event.target.value)}
                                maxLength={255}
                            />
                        </div>
                        <div className="app-field">
                            <label className="app-label" htmlFor="account-email">
                                {t('auth.email')}
                            </label>
                            <input
                                id="account-email"
                                className="app-input"
                                value={user.email ?? ''}
                                disabled
                            />
                            <p className="app-field__hint">{t('accountSettings.emailLocked')}</p>
                        </div>
                    </div>

                    {formError ? <p className="app-field__error">{formError}</p> : null}

                    <div className="app-action-row">
                        <button
                            type="submit"
                            className="app-button app-button--primary app-account-settings__submit"
                            disabled={saving || !name.trim() || !nameChanged}
                        >
                            {saving ? t('accountSettings.saving') : t('accountSettings.save')}
                        </button>
                    </div>
                </form>
            </section>

            <section
                id="linked-providers"
                className="app-form-card app-account-settings app-account-settings__providers-card"
            >
                <div className="app-account-settings__section-head">
                    <div>
                        <h3 className="app-form-card__title">
                            {t('accountSettings.linkedProvidersTitle')}
                        </h3>
                        <p className="app-form-card__text">
                            {t('accountSettings.linkedProvidersText')}
                        </p>
                    </div>
                    {providerError ? <p className="app-field__error">{providerError}</p> : null}
                </div>

                <div className="app-account-settings__provider-grid">
                    {linkedProviders.length > 0 ? (
                        linkedProviders.map((provider) => {
                            const isCurrentProvider = currentSignInProvider?.key === provider.key;

                            return (
                                <article
                                    key={provider.key}
                                    className="app-account-settings__provider-card"
                                >
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
                                            onClick={() => setConfirmProvider(provider)}
                                        >
                                            {unlinkingProviderKey === provider.key
                                                ? t('accountSettings.unlinking')
                                                : t('accountSettings.unlink')}
                                        </button>
                                    </div>
                                </article>
                            );
                        })
                    ) : (
                        <p className="app-field__hint">{t('accountSettings.noLinkedProviders')}</p>
                    )}
                </div>

                {linkableProviders.length > 0 ? (
                    <div className="app-account-settings__link-panel">
                        <div>
                            <h4 className="app-account-settings__subheading">
                                {t('accountSettings.availableProvidersTitle')}
                            </h4>
                            <p className="app-form-card__text">
                                {t('accountSettings.availableProvidersText')}
                            </p>
                        </div>
                        <div className="app-account-settings__link-actions">
                            {linkableProviders.map((provider) => (
                                <a
                                    key={provider.key}
                                    href={getRedirectUrl(provider)}
                                    className="app-account-settings__link-action"
                                >
                                    <AppIcon name={provider.icon ?? provider.key} />
                                    <span>
                                        {t('accountSettings.connectWith')} {provider.display_name}
                                    </span>
                                </a>
                            ))}
                        </div>
                    </div>
                ) : null}

                <div className="app-account-settings__help-row">
                    <p className="app-field__hint">{t('accountSettings.passwordHelp')}</p>
                    <Link to="/forgot-password" className="app-button app-button--ghost">
                        {t('accountSettings.resetPassword')}
                    </Link>
                </div>
            </section>

            <ConfirmModal
                open={confirmProvider !== null}
                eyebrow={t('accountSettings.confirmEyebrow')}
                title={t('accountSettings.confirmTitle')}
                text={
                    confirmProvider
                        ? `${t('accountSettings.confirmText')} ${confirmProvider.display_name}?`
                        : ''
                }
                confirmLabel={t('accountSettings.unlink')}
                cancelLabel={t('common.cancel')}
                tone="danger"
                onConfirm={unlinkProvider}
                onCancel={() => setConfirmProvider(null)}
            />
        </div>
    );
}
