import { useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import AuthPageShell from '../components/AuthPageShell';
import AuthPanelActionStrip from '../components/AuthPanelActionStrip';
import AuthPanelFlow from '../components/AuthPanelFlow';
import AuthPanelHeader from '../components/AuthPanelHeader';
import AuthPanelIntro from '../components/AuthPanelIntro';
import AuthPanelStack from '../components/AuthPanelStack';
import AuthPanelStatus from '../components/AuthPanelStatus';
import AuthShowcase from '../components/AuthShowcase';
import AuthProviderGroup from '../components/AuthProviderGroup';
import { useAuth } from '../context/AuthContext';
import { getNonFormProviders, getPasswordFormProvider } from '../lib/publicAuthProviders';
import { useLanguage } from '../../i18n/context/LanguageContext';
import RegisterForm from '../components/RegisterForm';
import { useRegisterForm } from '../hooks/useRegisterForm';

/**
 * Render the registration screen with password rules and provider-aware fallback actions.
 */
export default function RegisterPage() {
    const navigate = useNavigate();
    const { register, registerProviders, providersLoading, providersError } = useAuth();
    const { t } = useLanguage();
    const emailProvider = getPasswordFormProvider(registerProviders);
    const secondaryProviders = getNonFormProviders(registerProviders, emailProvider);
    const registerForm = useRegisterForm({ navigate, register, t });

    return (
        <AuthPageShell>
            <article className="app-auth-card app-auth-panel">
                <AuthPanelHeader t={t} />
                <AuthPanelFlow label="OPAS registration flow" />
                <AuthPanelIntro
                    eyebrow={t('auth.registerEyebrow')}
                    title={t('auth.registerTitle')}
                    text={t('auth.registerText')}
                />
                {providersLoading ? <AuthPanelStatus text={t('auth.providersLoading')} /> : null}
                {!providersLoading && providersError ? (
                    <ErrorState text={t('auth.providersLoadError')} />
                ) : null}
                {emailProvider?.capabilities?.register ? (
                    <RegisterForm
                        canSubmit={registerForm.canSubmit}
                        error={registerForm.error}
                        fieldErrors={registerForm.fieldErrors}
                        form={registerForm.form}
                        invalidEmail={registerForm.invalidEmail}
                        missingPasswordRuleKeys={registerForm.missingPasswordRuleKeys}
                        passwordMismatch={registerForm.passwordMismatch}
                        submitting={registerForm.submitting}
                        t={t}
                        weakPassword={registerForm.weakPassword}
                        onFormChange={registerForm.setForm}
                        onSubmit={registerForm.submit}
                    />
                ) : null}
                <AuthProviderGroup
                    action="register"
                    label={t('auth.loginProviderDivider')}
                    providers={secondaryProviders}
                    t={t}
                />
                {!providersLoading && !providersError && registerProviders.length === 0 ? (
                    <ErrorState text={t('auth.registrationUnavailable')} />
                ) : null}
                <AuthPanelStack />
                <AuthPanelActionStrip
                    label={t('auth.haveAccount')}
                    linkLabel={t('auth.goToLogin')}
                    to="/login"
                />
            </article>

            <AuthShowcase
                eyebrow={t('auth.registerEyebrow')}
                title={t('auth.registerShowcaseTitle')}
                text={t('auth.registerShowcaseText')}
                tags={[t('auth.member'), t('auth.plus'), t('auth.vip'), t('auth.admin')]}
                imageSrc="/images/auth/cyber-city.jpg"
            />
        </AuthPageShell>
    );
}
