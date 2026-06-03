import LoginAccessPanel from './LoginAccessPanel';
import AuthShowcase from './AuthShowcase';
import { useLoginPageViewModel } from '../hooks/useLoginPageViewModel';

/**
 * Compose the login screen from the auth view model.
 *
 * @returns {import('react').JSX.Element}
 */
export default function LoginPageContainer() {
    const { emailProvider, loginForm, providers, registration, t } = useLoginPageViewModel();

    return (
        <>
            <LoginAccessPanel
                emailProvider={emailProvider}
                loginForm={loginForm}
                providers={providers}
                registration={registration}
                t={t}
            />
            <AuthShowcase
                eyebrow={t('auth.loginEyebrow')}
                title={t('auth.loginShowcaseTitle')}
                text={t('auth.loginShowcaseText')}
                tags={[t('auth.marketAccess'), t('auth.contentControl'), t('auth.flowSecurity')]}
                imageSrc="/images/auth/cyber-city.jpg"
            />
        </>
    );
}
