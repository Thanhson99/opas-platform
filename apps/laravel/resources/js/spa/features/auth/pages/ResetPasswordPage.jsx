import { useMemo, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import api from '../../../lib/api';
import LanguageSelect from '../../../components/layout/LanguageSelect';
import ErrorState from '../../../components/ui/ErrorState';
import AuthShowcase from '../components/AuthShowcase';
import SensitiveInput from '../components/SensitiveInput';
import { getMissingPasswordRuleKeys, isStrongPassword } from '../lib/passwordValidation';
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
    const [form, setForm] = useState({
        email: params.get('email') ?? '',
        password: '',
        password_confirmation: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const weakPassword = form.password.trim() !== '' && !isStrongPassword(form.password);
    const missingPasswordRuleKeys = getMissingPasswordRuleKeys(form.password);
    const passwordMismatch =
        form.password_confirmation.trim() !== '' && form.password !== form.password_confirmation;
    const canSubmit =
        form.email.trim() !== '' &&
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()) &&
        form.password.trim() !== '' &&
        isStrongPassword(form.password) &&
        form.password_confirmation.trim() !== '' &&
        !passwordMismatch &&
        !submitting;

    const submit = async (event) => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        setSubmitting(true);
        setError('');

        try {
            await api.post('/auth/reset-password', {
                ...form,
                token,
            });
            navigate('/login?reset=success', { replace: true });
        } catch (requestError) {
            setError(
                requestError?.response?.data?.errors?.email?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.resetPasswordError'),
            );
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <div className="app-auth-floating-language">
                <LanguageSelect />
            </div>
            <div className="app-auth-screen">
                <section className="app-auth-layout">
                    <article className="app-form-card app-form-card--accent app-auth-panel">
                        <div className="app-auth-panel__topbar">
                            <span className="app-auth-panel__brand">
                                <img
                                    src="/storage/images/brand/opas-logo-mark.png"
                                    alt=""
                                    aria-hidden="true"
                                />
                                <span>{t('auth.account')}</span>
                            </span>
                            <div className="app-auth-panel__topbar-actions">
                                <Link to="/login" className="app-inline-link">
                                    {t('auth.goToLogin')}
                                </Link>
                            </div>
                        </div>
                        <h2 className="app-form-card__title">{t('auth.resetPasswordTitle')}</h2>
                        <p className="app-form-card__text">{t('auth.resetPasswordText')}</p>
                        {error ? <ErrorState text={error} /> : null}
                        <form className="app-form" onSubmit={submit}>
                            <div className="app-field">
                                <label className="app-label">{t('auth.email')}</label>
                                <input
                                    className="app-input"
                                    type="email"
                                    value={form.email}
                                    onChange={(event) =>
                                        setForm((value) => ({
                                            ...value,
                                            email: event.target.value,
                                        }))
                                    }
                                    required
                                />
                            </div>
                            <div className="app-field">
                                <label className="app-label">{t('auth.password')}</label>
                                <SensitiveInput
                                    value={form.password}
                                    invalid={weakPassword}
                                    required
                                    autoComplete="new-password"
                                    revealLabel={t('auth.showValue')}
                                    concealLabel={t('auth.hideValue')}
                                    onChange={(event) =>
                                        setForm((value) => ({
                                            ...value,
                                            password: event.target.value,
                                        }))
                                    }
                                />
                                {weakPassword ? (
                                    <>
                                        <p className="app-field__error">
                                            {t('auth.passwordRuleIntro')}
                                        </p>
                                        {missingPasswordRuleKeys.map((ruleKey) => (
                                            <p key={ruleKey} className="app-field__hint">
                                                {t('auth.passwordRuleFail')} {t(`auth.${ruleKey}`)}
                                            </p>
                                        ))}
                                    </>
                                ) : null}
                            </div>
                            <div className="app-field">
                                <label className="app-label">{t('auth.confirmPassword')}</label>
                                <SensitiveInput
                                    value={form.password_confirmation}
                                    invalid={passwordMismatch}
                                    required
                                    autoComplete="new-password"
                                    revealLabel={t('auth.showValue')}
                                    concealLabel={t('auth.hideValue')}
                                    onChange={(event) =>
                                        setForm((value) => ({
                                            ...value,
                                            password_confirmation: event.target.value,
                                        }))
                                    }
                                />
                                {passwordMismatch ? (
                                    <p className="app-field__error">
                                        {t('auth.passwordConfirmMismatch')}
                                    </p>
                                ) : null}
                            </div>
                            <button
                                className="app-button app-button--primary"
                                type="submit"
                                disabled={!canSubmit}
                            >
                                {submitting
                                    ? t('auth.resetPasswordSubmitting')
                                    : t('auth.resetPasswordSubmit')}
                            </button>
                        </form>
                    </article>

                    <AuthShowcase
                        eyebrow={t('auth.resetPasswordEyebrow')}
                        title={t('auth.resetPasswordShowcaseTitle')}
                        text={t('auth.resetPasswordShowcaseText')}
                        tags={[t('auth.password'), t('auth.email'), t('auth.member')]}
                        imageSrc="/storage/images/auth/opas-auth-hero.png"
                    />
                </section>
            </div>
        </>
    );
}
