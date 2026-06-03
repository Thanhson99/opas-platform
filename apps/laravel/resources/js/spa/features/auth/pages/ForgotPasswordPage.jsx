import AuthLoginLinkHeader from '../components/AuthLoginLinkHeader';
import AuthPageShell from '../components/AuthPageShell';
import AuthPanelFlow from '../components/AuthPanelFlow';
import AuthPanelIntro from '../components/AuthPanelIntro';
import AuthPanelStack from '../components/AuthPanelStack';
import AuthShowcase from '../components/AuthShowcase';
import ForgotPasswordForm from '../components/ForgotPasswordForm';
import { useForgotPasswordForm } from '../hooks/useForgotPasswordForm';
import { useLanguage } from '../../i18n/context/LanguageContext';

/**
 * Render the password-reset request screen for email-based recovery.
 */
export default function ForgotPasswordPage() {
    const { t } = useLanguage();
    const forgotPasswordForm = useForgotPasswordForm({ t });

    return (
        <AuthPageShell>
            <article className="app-auth-card app-auth-panel">
                <AuthLoginLinkHeader t={t} />
                <AuthPanelFlow label="OPAS password recovery flow" />
                <AuthPanelIntro
                    eyebrow={t('auth.forgotPasswordEyebrow')}
                    title={t('auth.forgotPasswordTitle')}
                    text={t('auth.forgotPasswordText')}
                />
                <ForgotPasswordForm
                    email={forgotPasswordForm.email}
                    error={forgotPasswordForm.error}
                    flash={forgotPasswordForm.flash}
                    invalidEmail={forgotPasswordForm.invalidEmail}
                    isValid={forgotPasswordForm.isValid}
                    submitting={forgotPasswordForm.submitting}
                    t={t}
                    onEmailChange={forgotPasswordForm.setEmail}
                    onSubmit={forgotPasswordForm.submit}
                />
                <AuthPanelStack />
            </article>

            <AuthShowcase
                eyebrow={t('auth.forgotPasswordEyebrow')}
                title={t('auth.forgotPasswordShowcaseTitle')}
                text={t('auth.forgotPasswordShowcaseText')}
                tags={[t('auth.email'), t('auth.password'), t('auth.automationAccess')]}
                imageSrc="/images/auth/cyber-city.jpg"
            />
        </AuthPageShell>
    );
}
