import ErrorState from '../../../components/ui/ErrorState';
import AuthProviderGroup from './AuthProviderGroup';
import AuthPanelStatus from './AuthPanelStatus';

/**
 * @typedef {import('../lib/publicAuthProviders').PublicAuthProvider} PublicAuthProvider
 * @typedef {import('../hooks/useLoginPageViewModel').LoginProvidersViewModel} LoginProvidersViewModel
 */

/**
 * Render provider loading or failure feedback.
 *
 * @param {{ providers: LoginProvidersViewModel, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element | null}
 */
export function LoginProviderFeedback({ providers, t }) {
    if (providers.loading) {
        return <AuthPanelStatus text={t('auth.providersLoading')} />;
    }

    if (providers.error) {
        return <ErrorState text={t('auth.providersLoadError')} />;
    }

    return null;
}

/**
 * Render redirect-capable social login providers.
 *
 * @param {{ providers: PublicAuthProvider[], t: (key: string) => string }} props
 * @returns {import('react').JSX.Element | null}
 */
export function LoginSocialProviders({ providers, t }) {
    if (providers.length === 0) {
        return null;
    }

    return (
        <AuthProviderGroup
            action="login"
            label={t('auth.loginProviderDivider')}
            providers={providers}
            t={t}
        />
    );
}

/**
 * Render the empty state when backend exposes no login provider.
 *
 * @param {{ providers: LoginProvidersViewModel, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element | null}
 */
export function LoginNoProvidersState({ providers, t }) {
    if (providers.loading || providers.error || providers.hasLoginProviders) {
        return null;
    }

    return <ErrorState text={t('auth.noProvidersAvailable')} />;
}
