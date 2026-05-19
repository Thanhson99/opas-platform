import { useState } from 'react';
import AppIcon from '../icons/AppIcon';
import { useAuth } from '../../features/auth/context/AuthContext';
import { Link, useNavigate } from 'react-router-dom';
import { useLanguage } from '../../features/i18n/context/LanguageContext';
import LanguageSelect from './LanguageSelect';

export default function Header({ title, description, onToggleSidebar }) {
    const navigate = useNavigate();
    const { user, loading, logout, registerProviders } = useAuth();
    const { t } = useLanguage();
    const [accountMenuOpen, setAccountMenuOpen] = useState(false);

    const handleLogout = async () => {
        setAccountMenuOpen(false);
        await logout();
        navigate('/', { replace: true });
    };

    return (
        <header className="opas-header">
            <button
                type="button"
                className="opas-header__toggle"
                id="sidebar-toggle"
                aria-label="Toggle navigation"
                onClick={onToggleSidebar}
            >
                <AppIcon name="menu" />
            </button>

            <div className="opas-header__intro">
                <p className="opas-header__eyebrow">OPAS</p>
                <h1 className="opas-header__title">{title}</h1>
                {description ? <p className="opas-header__text">{description}</p> : null}
            </div>

            <div className="opas-header__actions">
                {loading ? (
                    <div className="opas-account opas-account--muted">
                        {t('common.loadingAccount')}
                    </div>
                ) : user ? (
                    <div className="opas-account opas-account--menu">
                        <button
                            type="button"
                            className="opas-account__trigger"
                            aria-expanded={accountMenuOpen}
                            aria-label={t('header.accountMenu')}
                            onClick={() => setAccountMenuOpen((value) => !value)}
                        >
                            <div className="opas-account__meta">
                                <strong>{user.name}</strong>
                                <span>
                                    {user.role_label} • {user.email}
                                </span>
                            </div>
                            <AppIcon
                                name="chevron-down"
                                className={`opas-account__chevron ${
                                    accountMenuOpen ? 'is-open' : ''
                                }`}
                            />
                        </button>
                        {accountMenuOpen ? (
                            <div className="opas-account__dropdown">
                                <Link
                                    to="/account"
                                    className="opas-account__menu-link"
                                    onClick={() => setAccountMenuOpen(false)}
                                >
                                    <AppIcon name="users" />
                                    <span>{t('header.accountOverview')}</span>
                                </Link>
                            </div>
                        ) : null}
                        <button
                            type="button"
                            className="app-button app-button--ghost"
                            onClick={handleLogout}
                        >
                            {t('common.logout')}
                        </button>
                    </div>
                ) : (
                    <div className="opas-account">
                        <div className="opas-account__meta">
                            <strong>{t('header.guest')}</strong>
                            <span>{t('header.guestHint')}</span>
                        </div>
                        <Link to="/login" className="app-button app-button--ghost">
                            {t('common.login')}
                        </Link>
                        {registerProviders.length > 0 ? (
                            <Link to="/register" className="app-button app-button--primary">
                                {t('common.register')}
                            </Link>
                        ) : null}
                    </div>
                )}
                <LanguageSelect />
            </div>
        </header>
    );
}
