import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import AuthShowcase from '../components/AuthShowcase';
import SensitiveInput from '../components/SensitiveInput';
import { useAuth } from '../context/AuthContext';
import { getMissingPasswordRuleKeys, isStrongPassword } from '../lib/passwordValidation';
import { useLanguage } from '../../i18n/context/LanguageContext';
import LanguageSelect from '../../../components/layout/LanguageSelect';

export default function RegisterPage() {
    const navigate = useNavigate();
    const { register, authProviders, providersLoading, refreshAuthProviders } = useAuth();
    const { t } = useLanguage();
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});
    const emailProvider = authProviders.find((provider) => provider.key === 'email') ?? null;

    useEffect(() => {
        void refreshAuthProviders();
    }, [refreshAuthProviders]);

    const weakPassword = form.password.trim() !== '' && !isStrongPassword(form.password);
    const missingPasswordRuleKeys = getMissingPasswordRuleKeys(form.password);
    const passwordMismatch =
        form.password_confirmation.trim() !== '' && form.password !== form.password_confirmation;
    const invalidEmail =
        form.email.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim());
    const canSubmit =
        form.name.trim() !== '' &&
        form.email.trim() !== '' &&
        !invalidEmail &&
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
        setFieldErrors({});

        try {
            await register(form);
            navigate(`/verify-email?email=${encodeURIComponent(form.email)}&status=pending`, {
                replace: true,
            });
        } catch (requestError) {
            setFieldErrors(requestError?.response?.data?.errors ?? {});
            setError(requestError?.response?.data?.message || t('auth.registerError'));
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
                                <Link to="/" className="app-inline-link">
                                    {t('auth.backHome')}
                                </Link>
                            </div>
                        </div>
                        <h2 className="app-form-card__title">{t('auth.registerTitle')}</h2>
                        <p className="app-form-card__text">{t('auth.registerText')}</p>
                        {providersLoading ? <p>{t('auth.providersLoading')}</p> : null}
                        {emailProvider?.capabilities?.register ? (
                            <form className="app-form" onSubmit={submit}>
                                <div className="app-field">
                                    <label className="app-label">{t('auth.name')}</label>
                                    <input
                                        className={`app-input ${fieldErrors.name?.[0] ? 'app-input--invalid' : ''}`}
                                        value={form.name}
                                        onChange={(event) =>
                                            setForm((value) => ({
                                                ...value,
                                                name: event.target.value,
                                            }))
                                        }
                                        required
                                    />
                                    {fieldErrors.name?.[0] ? (
                                        <p className="app-field__error">{fieldErrors.name[0]}</p>
                                    ) : null}
                                </div>
                                <div className="app-field">
                                    <label className="app-label">{t('auth.email')}</label>
                                    <input
                                        className={`app-input ${
                                            invalidEmail || fieldErrors.email?.[0]
                                                ? 'app-input--invalid'
                                                : ''
                                        }`}
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
                                    {invalidEmail ? (
                                        <p className="app-field__error">{t('auth.invalidEmail')}</p>
                                    ) : null}
                                    {fieldErrors.email?.[0] ? (
                                        <p className="app-field__error">{fieldErrors.email[0]}</p>
                                    ) : null}
                                    {fieldErrors.email?.[0] ? (
                                        <p className="app-field__hint">
                                            <Link to="/login" className="app-inline-link">
                                                {t('auth.goToLogin')}
                                            </Link>
                                        </p>
                                    ) : null}
                                </div>
                                <div className="app-field">
                                    <label className="app-label">{t('auth.password')}</label>
                                    <SensitiveInput
                                        value={form.password}
                                        invalid={Boolean(weakPassword || fieldErrors.password?.[0])}
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
                                                    {t('auth.passwordRuleFail')}{' '}
                                                    {t(`auth.${ruleKey}`)}
                                                </p>
                                            ))}
                                        </>
                                    ) : null}
                                    {fieldErrors.password?.[0] ? (
                                        <p className="app-field__error">
                                            {fieldErrors.password[0]}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="app-field">
                                    <label className="app-label">{t('auth.confirmPassword')}</label>
                                    <SensitiveInput
                                        value={form.password_confirmation}
                                        invalid={Boolean(
                                            passwordMismatch ||
                                            fieldErrors.password_confirmation?.[0],
                                        )}
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
                                    {fieldErrors.password_confirmation?.[0] ? (
                                        <p className="app-field__error">
                                            {fieldErrors.password_confirmation[0]}
                                        </p>
                                    ) : null}
                                </div>
                                {error ? <ErrorState text={error} /> : null}
                                <button
                                    className="app-button app-button--primary"
                                    type="submit"
                                    disabled={!canSubmit}
                                >
                                    {submitting
                                        ? t('auth.registerSubmitting')
                                        : t('auth.registerSubmit')}
                                </button>
                            </form>
                        ) : !providersLoading ? (
                            <ErrorState text={t('auth.registrationUnavailable')} />
                        ) : null}
                        <div className="app-auth-panel__footer">
                            <span>{t('auth.haveAccount')}</span>
                            <Link to="/login" className="app-inline-link">
                                {t('auth.goToLogin')}
                            </Link>
                        </div>
                    </article>

                    <AuthShowcase
                        eyebrow={t('auth.registerEyebrow')}
                        title={t('auth.registerShowcaseTitle')}
                        text={t('auth.registerShowcaseText')}
                        tags={[t('auth.member'), t('auth.plus'), t('auth.vip'), t('auth.admin')]}
                        imageSrc="/storage/images/auth/opas-auth-hero.png"
                    />
                </section>
            </div>
        </>
    );
}
