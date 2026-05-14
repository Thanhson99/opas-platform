import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import AuthShowcase from '../components/AuthShowcase';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../../i18n/context/LanguageContext';
import LanguageSelect from '../../../components/layout/LanguageSelect';

export default function RegisterPage() {
    const navigate = useNavigate();
    const { register } = useAuth();
    const { t } = useLanguage();
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setError('');

        try {
            await register(form);
            navigate('/', { replace: true });
        } catch (requestError) {
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
                        <form className="app-form" onSubmit={submit}>
                            <div className="app-field">
                                <label className="app-label">{t('auth.name')}</label>
                                <input
                                    className="app-input"
                                    value={form.name}
                                    onChange={(event) =>
                                        setForm((value) => ({ ...value, name: event.target.value }))
                                    }
                                    required
                                />
                            </div>
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
                            <div className="app-field">
                                <label className="app-label">{t('auth.confirmPassword')}</label>
                                <input
                                    className="app-input"
                                    type="password"
                                    value={form.password_confirmation}
                                    onChange={(event) =>
                                        setForm((value) => ({
                                            ...value,
                                            password_confirmation: event.target.value,
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
                                {submitting
                                    ? t('auth.registerSubmitting')
                                    : t('auth.registerSubmit')}
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
