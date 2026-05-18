import { useMemo, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import api from '../../../lib/api';
import LanguageSelect from '../../../components/layout/LanguageSelect';
import ErrorState from '../../../components/ui/ErrorState';
import AuthShowcase from '../components/AuthShowcase';
import { useLanguage } from '../../i18n/context/LanguageContext';

export default function VerifyEmailPage() {
    const location = useLocation();
    const { t } = useLanguage();
    const params = useMemo(() => new URLSearchParams(location.search), [location.search]);
    const [email, setEmail] = useState(params.get('email') ?? '');
    const [code, setCode] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [resending, setResending] = useState(false);
    const [flash, setFlash] = useState('');
    const [error, setError] = useState('');
    const [status, setStatus] = useState(params.get('status') ?? 'pending');

    const statusMessage = (() => {
        if (status === 'verified') {
            return t('auth.verifyEmailVerified');
        }

        if (status === 'already-verified') {
            return t('auth.verifyEmailAlreadyVerified');
        }

        if (status === 'expired') {
            return t('auth.verifyEmailExpired');
        }

        if (status === 'invalid') {
            return t('auth.verifyEmailInvalid');
        }

        return t('auth.verifyEmailPending');
    })();

    const submit = async (event) => {
        event.preventDefault();
        setVerifying(true);
        setFlash('');
        setError('');

        try {
            const response = await api.post('/auth/email/verify', { email, code });
            setStatus(response?.data?.meta?.status ?? 'verified');
            setFlash(response.data.message ?? t('auth.verifyEmailVerified'));
        } catch (requestError) {
            setStatus(requestError?.response?.data?.meta?.status ?? 'invalid');
            setError(
                requestError?.response?.data?.errors?.code?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.verifyEmailError'),
            );
        } finally {
            setVerifying(false);
        }
    };

    const resendCode = async () => {
        setResending(true);
        setFlash('');
        setError('');

        try {
            const response = await api.post('/auth/email/verification-notification', { email });
            setStatus('pending');
            setFlash(response.data.message ?? t('auth.verifyEmailResent'));
        } catch (requestError) {
            setError(
                requestError?.response?.data?.errors?.email?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.verifyEmailResendError'),
            );
        } finally {
            setResending(false);
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
                        <h2 className="app-form-card__title">{t('auth.verifyEmailTitle')}</h2>
                        <p className="app-form-card__text">{statusMessage}</p>
                        {flash ? (
                            <div className="app-provider-note app-provider-note--success">
                                {flash}
                            </div>
                        ) : null}
                        {error ? <ErrorState text={error} /> : null}
                        <form className="app-form" onSubmit={submit}>
                            <div className="app-field">
                                <label className="app-label">{t('auth.email')}</label>
                                <input
                                    className="app-input"
                                    type="email"
                                    value={email}
                                    onChange={(event) => setEmail(event.target.value)}
                                    required
                                />
                            </div>
                            <div className="app-field">
                                <label className="app-label">{t('auth.verifyEmailCode')}</label>
                                <input
                                    className="app-input"
                                    type="text"
                                    inputMode="numeric"
                                    pattern="[0-9]*"
                                    maxLength={6}
                                    value={code}
                                    onChange={(event) =>
                                        setCode(event.target.value.replace(/\D/g, '').slice(0, 6))
                                    }
                                    required
                                />
                                <p className="app-field__hint">{t('auth.verifyEmailCodeHint')}</p>
                            </div>
                            <button
                                className="app-button app-button--primary"
                                type="submit"
                                disabled={verifying || email.trim() === '' || code.trim().length !== 6}
                            >
                                {verifying
                                    ? t('auth.verifyEmailVerifying')
                                    : t('auth.verifyEmailSubmit')}
                            </button>
                            <button
                                className="app-button app-button--secondary"
                                type="button"
                                onClick={resendCode}
                                disabled={resending || email.trim() === ''}
                            >
                                {resending
                                    ? t('auth.verifyEmailSending')
                                    : t('auth.verifyEmailSend')}
                            </button>
                        </form>
                        <div className="app-auth-panel__footer">
                            <span>{t('auth.haveAccount')}</span>
                            <Link to="/login" className="app-inline-link">
                                {t('auth.goToLogin')}
                            </Link>
                        </div>
                    </article>

                    <AuthShowcase
                        eyebrow={t('auth.verifyEmailEyebrow')}
                        title={t('auth.verifyEmailShowcaseTitle')}
                        text={t('auth.verifyEmailShowcaseText')}
                        tags={[t('auth.email'), t('auth.member'), t('auth.automationAccess')]}
                        imageSrc="/storage/images/auth/opas-auth-hero.png"
                    />
                </section>
            </div>
        </>
    );
}
