import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../../lib/api';
import LanguageSelect from '../../../components/layout/LanguageSelect';
import ErrorState from '../../../components/ui/ErrorState';
import AuthShowcase from '../components/AuthShowcase';
import { useLanguage } from '../../i18n/context/LanguageContext';

/**
 * Render the password-reset request screen for email-based recovery.
 */
export default function ForgotPasswordPage() {
    const { t } = useLanguage();
    const [email, setEmail] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [flash, setFlash] = useState('');
    const [error, setError] = useState('');

    const isValid = email.trim() !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());

    const submit = async (event) => {
        event.preventDefault();

        if (!isValid) {
            return;
        }

        setSubmitting(true);
        setFlash('');
        setError('');

        try {
            const response = await api.post('/auth/forgot-password', { email });
            setFlash(response.data.message ?? t('auth.forgotPasswordSent'));
        } catch (requestError) {
            setError(
                requestError?.response?.data?.errors?.email?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.forgotPasswordError'),
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
                        <h2 className="app-form-card__title">{t('auth.forgotPasswordTitle')}</h2>
                        <p className="app-form-card__text">{t('auth.forgotPasswordText')}</p>
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
                                disabled={!isValid || submitting}
                            >
                                {submitting
                                    ? t('auth.forgotPasswordSending')
                                    : t('auth.forgotPasswordSubmit')}
                            </button>
                        </form>
                    </article>

                    <AuthShowcase
                        eyebrow={t('auth.forgotPasswordEyebrow')}
                        title={t('auth.forgotPasswordShowcaseTitle')}
                        text={t('auth.forgotPasswordShowcaseText')}
                        tags={[t('auth.email'), t('auth.password'), t('auth.automationAccess')]}
                        imageSrc="/storage/images/auth/opas-auth-hero.png"
                    />
                </section>
            </div>
        </>
    );
}
