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
    const [submitting, setSubmitting] = useState(false);
    const [flash, setFlash] = useState('');
    const [error, setError] = useState('');
    const status = params.get('status') ?? 'pending';

    const statusMessage = (() => {
        if (status === 'verified') {
            return t('auth.verifyEmailVerified');
        }

        if (status === 'already-verified') {
            return t('auth.verifyEmailAlreadyVerified');
        }

        if (status === 'invalid') {
            return t('auth.verifyEmailInvalid');
        }

        return t('auth.verifyEmailPending');
    })();

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setFlash('');
        setError('');

        try {
            const response = await api.post('/auth/email/verification-notification', { email });
            setFlash(response.data.message ?? t('auth.verifyEmailResent'));
        } catch (requestError) {
            setError(
                requestError?.response?.data?.errors?.email?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.verifyEmailResendError'),
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
                            <button
                                className="app-button app-button--primary"
                                type="submit"
                                disabled={submitting}
                            >
                                {submitting
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
