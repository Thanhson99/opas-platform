import { useMemo } from 'react';
import { useLocation } from 'react-router-dom';
import AuthLoginLinkHeader from '../components/AuthLoginLinkHeader';
import AuthPageShell from '../components/AuthPageShell';
import AuthPanelActionStrip from '../components/AuthPanelActionStrip';
import AuthPanelFlow from '../components/AuthPanelFlow';
import AuthPanelIntro from '../components/AuthPanelIntro';
import AuthPanelStack from '../components/AuthPanelStack';
import AuthShowcase from '../components/AuthShowcase';
import VerifyEmailForm from '../components/VerifyEmailForm';
import { useVerifyEmailForm } from '../hooks/useVerifyEmailForm';
import { useLanguage } from '../../i18n/context/LanguageContext';

/**
 * Render the email verification screen for code entry and resend actions.
 */
export default function VerifyEmailPage() {
    const location = useLocation();
    const { t } = useLanguage();
    const params = useMemo(() => new URLSearchParams(location.search), [location.search]);
    const verifyEmailForm = useVerifyEmailForm({
        initialEmail: params.get('email') ?? '',
        initialStatus: params.get('status') ?? 'pending',
        t,
    });

    return (
        <AuthPageShell>
            <article className="app-auth-card app-auth-panel">
                <AuthLoginLinkHeader t={t} />
                <AuthPanelFlow label="OPAS email verification flow" />
                <AuthPanelIntro
                    eyebrow={t('auth.verifyEmailEyebrow')}
                    title={t('auth.verifyEmailTitle')}
                    text={verifyEmailForm.statusMessage}
                />
                <VerifyEmailForm
                    canResend={verifyEmailForm.canResend}
                    canVerify={verifyEmailForm.canVerify}
                    code={verifyEmailForm.code}
                    email={verifyEmailForm.email}
                    error={verifyEmailForm.error}
                    flash={verifyEmailForm.flash}
                    resending={verifyEmailForm.resending}
                    t={t}
                    verifying={verifyEmailForm.verifying}
                    onCodeChange={verifyEmailForm.setCode}
                    onEmailChange={verifyEmailForm.setEmail}
                    onResend={verifyEmailForm.resendCode}
                    onSubmit={verifyEmailForm.submit}
                />
                <AuthPanelStack />
                <AuthPanelActionStrip
                    label={t('auth.haveAccount')}
                    linkLabel={t('auth.goToLogin')}
                    to="/login"
                />
            </article>

            <AuthShowcase
                eyebrow={t('auth.verifyEmailEyebrow')}
                title={t('auth.verifyEmailShowcaseTitle')}
                text={t('auth.verifyEmailShowcaseText')}
                tags={[t('auth.email'), t('auth.member'), t('auth.automationAccess')]}
                imageSrc="/storage/images/auth/opas-auth-hero.png"
            />
        </AuthPageShell>
    );
}
