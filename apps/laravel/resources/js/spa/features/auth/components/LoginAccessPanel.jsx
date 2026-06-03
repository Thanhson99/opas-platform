import AuthCardCorners from './AuthCardCorners';
import AuthPanelHeader from './AuthPanelHeader';
import LoginCardTitle from './LoginCardTitle';
import LoginFormPanel from './LoginFormPanel';
import {
    LoginNoProvidersState,
    LoginProviderFeedback,
    LoginSocialProviders,
} from './LoginProviderSections';
import LoginRegistrationFooter from './LoginRegistrationFooter';

/**
 * @typedef {import('../lib/publicAuthProviders').PublicAuthProvider} PublicAuthProvider
 * @typedef {import('../hooks/useLoginPageViewModel').LoginFormViewModel} LoginFormViewModel
 * @typedef {import('../hooks/useLoginPageViewModel').LoginProvidersViewModel} LoginProvidersViewModel
 * @typedef {import('../hooks/useLoginPageViewModel').LoginRegistrationViewModel} LoginRegistrationViewModel
 */

/**
 * Render the cyberpunk login access card.
 *
 * @param {{
 *   emailProvider: PublicAuthProvider | null,
 *   loginForm: LoginFormViewModel,
 *   providers: LoginProvidersViewModel,
 *   registration: LoginRegistrationViewModel,
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function LoginAccessPanel({ emailProvider, loginForm, providers, registration, t }) {
    return (
        <article className="app-auth-login-card app-auth-panel">
            <AuthCardCorners />
            <AuthPanelHeader t={t} />
            <LoginCardTitle t={t} />

            <LoginProviderFeedback providers={providers} t={t} />
            {emailProvider ? <LoginFormPanel loginForm={loginForm} t={t} /> : null}
            <LoginSocialProviders providers={providers.items} t={t} />
            <LoginNoProvidersState providers={providers} t={t} />
            <LoginRegistrationFooter providers={registration.providers} t={t} />
        </article>
    );
}
