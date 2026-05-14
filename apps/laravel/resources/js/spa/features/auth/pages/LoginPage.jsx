import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import AuthShowcase from '../components/AuthShowcase';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../../i18n/context/LanguageContext';
import LanguageSelect from '../../../components/layout/LanguageSelect';

export default function LoginPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { login } = useAuth();
    const { t } = useLanguage();
    const [form, setForm] = useState({
        email: '',
        password: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const from = location.state?.from?.pathname || '/';

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setError('');

        try {
            await login(form);
            navigate(from, { replace: true });
        } catch (requestError) {
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
                                <input
                                    className="app-input"
                                    type="password"
                                    value={form.password}
                                    onChange={(event) =>
                                        setForm((value) => ({
                                            ...value,
                                            password: event.target.value,
                                        }))
                                    }
                                    required
                                />
                            </div>
                            {error ? <ErrorState text={error} /> : null}
                            <button
                                className="app-button app-button--primary"
                                type="submit"
                                disabled={submitting}
                            >
                                {submitting ? t('auth.loginSubmitting') : t('auth.loginSubmit')}
                            </button>
                        </form>
                        <div className="app-auth-panel__footer">
                            <span>{t('auth.noAccount')}</span>
                            <Link to="/register" className="app-inline-link">
                                {t('auth.createAccount')}
                            </Link>
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
