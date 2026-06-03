import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import LoadingState from '../../../components/ui/LoadingState';
import AccountProfileSection from '../components/AccountProfileSection';
import AccountSettingsHero from '../components/AccountSettingsHero';
import LinkedProvidersSection from '../components/LinkedProvidersSection';
import { useAccountSettings } from '../hooks/useAccountSettings';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../../i18n/context/LanguageContext';

/**
 * Render profile and linked-provider management for the authenticated account.
 */
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

    useEffect(() => {
        if (!loading && !isAuthenticated) {
            navigate('/login', {
                replace: true,
                state: { from: { pathname: '/account' } },
            });
        }
    }, [isAuthenticated, loading, navigate]);

    if (loading || !user) {
        return <LoadingState text={t('common.loadingAccount')} />;
    }

    return (
        <AccountSettingsContent
            hasEmailLogin={hasEmailLogin}
            linkableProviders={linkableProviders}
            refreshAuthProviders={refreshAuthProviders}
            refreshUser={refreshUser}
            t={t}
            user={user}
        />
    );
}

function AccountSettingsContent({
    hasEmailLogin,
    linkableProviders,
    refreshAuthProviders,
    refreshUser,
    t,
    user,
}) {
    const accountSettings = useAccountSettings({
        refreshAuthProviders,
        refreshUser,
        t,
        user,
    });

    return (
        <div className="app-shell app-account-settings-page">
            <AccountSettingsHero
                currentSignInProvider={accountSettings.currentSignInProvider}
                hasEmailLogin={hasEmailLogin}
                t={t}
            />

            <AccountProfileSection
                flash={accountSettings.flash}
                formError={accountSettings.formError}
                name={accountSettings.name}
                nameChanged={accountSettings.nameChanged}
                saving={accountSettings.saving}
                t={t}
                user={user}
                onNameChange={accountSettings.setName}
                onSubmit={accountSettings.saveProfile}
            />

            <LinkedProvidersSection
                currentSignInProvider={accountSettings.currentSignInProvider}
                linkableProviders={linkableProviders}
                linkedProviders={accountSettings.linkedProviders}
                providerError={accountSettings.providerError}
                t={t}
                unlinkingProviderKey={accountSettings.unlinkingProviderKey}
                onConfirmProvider={accountSettings.setConfirmProvider}
            />

            <ConfirmModal
                open={accountSettings.confirmProvider !== null}
                eyebrow={t('accountSettings.confirmEyebrow')}
                title={t('accountSettings.confirmTitle')}
                text={
                    accountSettings.confirmProvider
                        ? `${t('accountSettings.confirmText')} ${
                              accountSettings.confirmProvider.display_name
                          }?`
                        : ''
                }
                confirmLabel={t('accountSettings.unlink')}
                cancelLabel={t('common.cancel')}
                tone="danger"
                confirmDisabled={Boolean(
                    accountSettings.confirmProvider &&
                    accountSettings.unlinkingProviderKey === accountSettings.confirmProvider.key,
                )}
                cancelDisabled={Boolean(
                    accountSettings.confirmProvider &&
                    accountSettings.unlinkingProviderKey === accountSettings.confirmProvider.key,
                )}
                onConfirm={accountSettings.unlinkProvider}
                onCancel={() => accountSettings.setConfirmProvider(null)}
            />
        </div>
    );
}
