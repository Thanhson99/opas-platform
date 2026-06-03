import AuthPanelActionStrip from './AuthPanelActionStrip';

/**
 * @typedef {import('../lib/publicAuthProviders').PublicAuthProvider} PublicAuthProvider
 */

/**
 * Render registration availability from backend provider state.
 *
 * @param {{ providers: PublicAuthProvider[], t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function LoginRegistrationFooter({ providers, t }) {
    return (
        <AuthPanelActionStrip
            label={t('auth.noAccount')}
            linkLabel={providers.length > 0 ? t('auth.createAccount') : ''}
            to={providers.length > 0 ? '/register' : ''}
            fallback={t('auth.registrationUnavailable')}
        />
    );
}
