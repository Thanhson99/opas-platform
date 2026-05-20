import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import AuthShowcase from '../components/AuthShowcase';
import AuthProviderOptions from '../components/AuthProviderOptions';
import SensitiveInput from '../components/SensitiveInput';
import { useAuth } from '../context/AuthContext';
import { getNonFormProviders, getPasswordFormProvider } from '../lib/publicAuthProviders';
import { useLanguage } from '../../i18n/context/LanguageContext';
import LanguageSelect from '../../../components/layout/LanguageSelect';

/**
 * Render the login screen with dynamic provider discovery and email fallback handling.
 */
export default function LoginPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { login, loginProviders, registerProviders, providersLoading, providersError } =
        useAuth();
    const { t } = useLanguage();
    const [form, setForm] = useState({
        email: '',
        password: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [flash, setFlash] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});

    const from = location.state?.from?.pathname || '/';
    const emailProvider = getPasswordFormProvider(loginProviders);
    const secondaryProviders = getNonFormProviders(loginProviders, emailProvider);

    useEffect(() => {
        const params = new URLSearchParams(location.search);
        const authError = params.get('auth_error');
        const reset = params.get('reset');

        setFlash('');

        if (authError) {
            setError(authError);
        }

        if (reset === 'success') {
            setError('');
            setFlash(t('auth.resetPasswordSuccess'));
        }
    }, [location.search, t]);

    const invalidEmail =
        form.email.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim());
    const canSubmit =
        form.email.trim() !== '' && !invalidEmail && form.password.trim() !== '' && !submitting;

    const submit = async (event) => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        setSubmitting(true);
        setError('');
        setFieldErrors({});

        try {
            await login(form);
            navigate(from, { replace: true });
        } catch (requestError) {
            if (requestError?.response?.data?.meta?.verification_required) {
                const email = requestError.response.data.meta.email ?? form.email;
                navigate(`/verify-email?email=${encodeURIComponent(email)}&status=pending`, {
                    replace: true,
                });
                return;
            }

            setFieldErrors(requestError?.response?.data?.errors ?? {});
            setError(requestError?.response?.data?.message || t('auth.loginError'));
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
                        <h2 className="app-form-card__title">{t('auth.loginTitle')}</h2>
                        <p className="app-form-card__text">{t('auth.loginText')}</p>
                        {providersLoading ? <p>{t('auth.providersLoading')}</p> : null}
                        {!providersLoading && providersError ? (
                            <ErrorState text={t('auth.providersLoadError')} />
                        ) : null}
                        {emailProvider ? (
                            <form className="app-form" onSubmit={submit}>
                                <div className="app-field">
                                    <label className="app-label" htmlFor="login-email">
                                        {t('auth.email')}
                                    </label>
                                    <input
                                        id="login-email"
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
                                </div>
                                <div className="app-field">
                                    <label className="app-label" htmlFor="login-password">
                                        {t('auth.password')}
                                    </label>
                                    <SensitiveInput
                                        id="login-password"
                                        value={form.password}
                                        invalid={Boolean(fieldErrors.password?.[0] || error)}
                                        required
                                        autoComplete="current-password"
                                        revealLabel={t('auth.showValue')}
                                        concealLabel={t('auth.hideValue')}
                                        onChange={(event) =>
                                            setForm((value) => ({
                                                ...value,
                                                password: event.target.value,
                                            }))
                                        }
                                    />
                                    {fieldErrors.password?.[0] ? (
                                        <p className="app-field__error">
                                            {fieldErrors.password[0]}
                                        </p>
                                    ) : null}
                                </div>
                                {flash ? (
                                    <div
                                        className="app-provider-note app-provider-note--success"
                                        aria-live="polite"
                                    >
                                        {flash}
                                    </div>
                                ) : null}
                                {error ? <ErrorState text={error} /> : null}
                                <button
                                    className="app-button app-button--primary"
                                    type="submit"
                                    disabled={!canSubmit}
                                >
                                    {submitting ? t('auth.loginSubmitting') : t('auth.loginSubmit')}
                                </button>
                                <Link to="/forgot-password" className="app-inline-link">
                                    {t('auth.forgotPasswordLink')}
                                </Link>
                            </form>
                        ) : null}
                        <AuthProviderOptions providers={secondaryProviders} action="login" t={t} />
                        {!providersLoading && !providersError && loginProviders.length === 0 ? (
                            <ErrorState text={t('auth.noProvidersAvailable')} />
                        ) : null}
                        <div className="app-auth-panel__footer">
                            <span>{t('auth.noAccount')}</span>
                            {registerProviders.length > 0 ? (
                                <Link to="/register" className="app-inline-link">
                                    {t('auth.createAccount')}
                                </Link>
                            ) : (
                                <span aria-live="polite">{t('auth.registrationUnavailable')}</span>
                            )}
                        </div>
                    </article>

                    <AuthShowcase
                        eyebrow={t('auth.loginEyebrow')}
                        title={t('auth.loginShowcaseTitle')}
                        text={t('auth.loginShowcaseText')}
                        tags={[
                            t('auth.marketTracking'),
                            t('auth.contentWorkflow'),
                            t('auth.automationAccess'),
                        ]}
                        imageSrc="/storage/images/auth/opas-auth-hero.png"
                    />
                </section>
            </div>
        </>
    );
}
