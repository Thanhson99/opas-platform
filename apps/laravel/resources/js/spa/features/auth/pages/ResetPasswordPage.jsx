import { useMemo } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import AuthLoginLinkHeader from '../components/AuthLoginLinkHeader';
import AuthPageShell from '../components/AuthPageShell';
import AuthPanelFlow from '../components/AuthPanelFlow';
import AuthPanelIntro from '../components/AuthPanelIntro';
import AuthPanelStack from '../components/AuthPanelStack';
import AuthShowcase from '../components/AuthShowcase';
import ResetPasswordForm from '../components/ResetPasswordForm';
import { useResetPasswordForm } from '../hooks/useResetPasswordForm';
import { useLanguage } from '../../i18n/context/LanguageContext';

/**
 * Render the password reset completion screen for one reset token.
 */
export default function ResetPasswordPage() {
    const { token = '' } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const { t } = useLanguage();
    const params = useMemo(() => new URLSearchParams(location.search), [location.search]);
    const resetPasswordForm = useResetPasswordForm({
        initialEmail: params.get('email') ?? '',
        navigate,
        token,
        t,
    });

    return (
        <AuthPageShell>
            <article className="app-auth-card app-auth-panel">
                <AuthLoginLinkHeader t={t} />
                <AuthPanelFlow label="OPAS password reset flow" />
                <AuthPanelIntro
                    eyebrow={t('auth.resetPasswordEyebrow')}
                    title={t('auth.resetPasswordTitle')}
                    text={t('auth.resetPasswordText')}
                />
                <ResetPasswordForm
                    canSubmit={resetPasswordForm.canSubmit}
                    error={resetPasswordForm.error}
                    form={resetPasswordForm.form}
                    invalidEmail={resetPasswordForm.invalidEmail}
                    missingPasswordRuleKeys={resetPasswordForm.missingPasswordRuleKeys}
                    passwordMismatch={resetPasswordForm.passwordMismatch}
                    submitting={resetPasswordForm.submitting}
                    t={t}
                    weakPassword={resetPasswordForm.weakPassword}
                    onFormChange={resetPasswordForm.setForm}
                    onSubmit={resetPasswordForm.submit}
                />
                <AuthPanelStack />
            </article>

            <AuthShowcase
                eyebrow={t('auth.resetPasswordEyebrow')}
                title={t('auth.resetPasswordShowcaseTitle')}
                text={t('auth.resetPasswordShowcaseText')}
                tags={[t('auth.password'), t('auth.email'), t('auth.member')]}
                imageSrc="/images/auth/cyber-city.jpg"
            />
        </AuthPageShell>
    );
}
