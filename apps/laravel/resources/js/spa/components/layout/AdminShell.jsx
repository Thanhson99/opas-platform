import { useEffect, useState } from 'react';
import { Navigate, NavLink, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import AppIcon from '../icons/AppIcon';
import LanguageSelect from './LanguageSelect';
import ConfirmModal from '../ui/ConfirmModal';
import Footer from './Footer';
import api from '../../lib/api';
import { useAuth } from '../../features/auth/context/AuthContext';
import { useLanguage } from '../../features/i18n/context/LanguageContext';
import AuthProvidersDashboardPage from '../../features/auth/pages/admin/AuthProvidersDashboardPage';
import AuthProviderAdminPage from '../../features/auth/pages/admin/AuthProviderAdminPage';
import AdminUsersPage from '../../features/auth/pages/admin/AdminUsersPage';

export default function AdminShell() {
    const navigate = useNavigate();
    const location = useLocation();
    const { user, loading, logout } = useAuth();
    const { t } = useLanguage();
    const [logoutConfirmOpen, setLogoutConfirmOpen] = useState(false);
    const [authMenuOpen, setAuthMenuOpen] = useState(true);
    const [authProviderMenuItems, setAuthProviderMenuItems] = useState([]);

    useEffect(() => {
        const loadAdminProviders = async () => {
            try {
                const response = await api.get('/admin/auth/providers');
                const providers = response.data.data ?? [];

                setAuthProviderMenuItems(
                    providers.map((provider) => ({
                        href: `/admin/auth/providers/${provider.key}`,
                        label: provider.display_name,
                        icon: provider.icon,
                    })),
                );
            } catch {
                setAuthProviderMenuItems([]);
            }
        };

        void loadAdminProviders();
    }, []);

    if (!loading && user?.role !== 'admin') {
        return <Navigate to="/login" replace />;
    }

    const handleLogout = async () => {
        try {
            await logout();
        } finally {
            navigate('/login', { replace: true });
        }
    };

    const authProvidersActive = location.pathname.startsWith('/admin/auth/providers');
    const usersActive = location.pathname.startsWith('/admin/users');

    return (
        <div className="admin-console">
            <header className="admin-console__header">
                <div className="admin-console__header-brand">
                    <NavLink to="/admin/users" className="admin-console__brand">
                        <span className="admin-console__brand-mark" aria-hidden="true">
                            <img src="/storage/images/brand/opas-logo-mark.png" alt="" />
                        </span>
                        <span className="admin-console__brand-copy">
                            <strong>{t('adminConsole.brandTitle')}</strong>
                            <small>{t('adminConsole.brandText')}</small>
                        </span>
                    </NavLink>
                </div>

                <div className="admin-console__header-main">
                    <div className="admin-console__intro">
                        <p className="admin-console__eyebrow">{t('adminConsole.eyebrow')}</p>
                        <h1 className="admin-console__title">
                            {usersActive
                                ? t('adminUsers.menuLabel')
                                : t('shell.authProvidersTitle')}
                        </h1>
                    </div>

                    <div className="admin-console__actions">
                        <div className="admin-console__account">
                            <strong>{user?.name ?? t('header.guest')}</strong>
                            <span>{user?.email ?? ''}</span>
                        </div>
                        <LanguageSelect />
                        <button
                            type="button"
                            className="app-button app-button--ghost"
                            onClick={() => setLogoutConfirmOpen(true)}
                        >
                            {t('common.logout')}
                        </button>
                    </div>
                </div>
            </header>

            <div className="admin-console__body">
                <aside className="admin-console__sidebar">
                    <div className="admin-console__section">
                        <div className="admin-console__nav">
                            <NavLink
                                to="/admin/users"
                                className={({ isActive }) =>
                                    `admin-console__nav-link ${isActive ? 'is-active' : ''}`
                                }
                            >
                                <AppIcon name="users" />
                                <span>{t('adminUsers.menuLabel')}</span>
                            </NavLink>
                            <div className="admin-console__nav-group">
                                <div
                                    className={`admin-console__nav-parent ${
                                        authProvidersActive ? 'is-active' : ''
                                    }`}
                                >
                                    <NavLink
                                        to="/admin/auth/providers"
                                        className="admin-console__nav-link admin-console__nav-link--parent"
                                        onClick={() => {
                                            setAuthMenuOpen((value) =>
                                                authProvidersActive ? !value : true,
                                            );
                                        }}
                                    >
                                        <AppIcon name="shield" />
                                        <span>{t('nav.authProviders')}</span>
                                    </NavLink>
                                    <button
                                        type="button"
                                        className={`admin-console__nav-toggle ${
                                            authMenuOpen ? 'is-open' : ''
                                        }`}
                                        onClick={() => setAuthMenuOpen((value) => !value)}
                                        aria-expanded={authMenuOpen}
                                        aria-label={t('adminAuth.menu.toggle')}
                                    >
                                        <AppIcon name="chevron-down" />
                                    </button>
                                </div>
                                {authMenuOpen ? (
                                    <div className="admin-console__subnav">
                                        {authProviderMenuItems.map((item) => (
                                            <NavLink
                                                key={item.href}
                                                to={item.href}
                                                className={({ isActive }) =>
                                                    `admin-console__subnav-link ${isActive ? 'is-active' : ''}`
                                                }
                                            >
                                                <AppIcon name={item.icon} />
                                                <span>{item.label}</span>
                                            </NavLink>
                                        ))}
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </aside>

                <main className="admin-console__content">
                    <div className="admin-console__content-main">
                        <Routes>
                            <Route path="/users" element={<AdminUsersPage />} />
                            <Route
                                path="/auth/providers"
                                element={<AuthProvidersDashboardPage />}
                            />
                            <Route
                                path="/auth/providers/:key"
                                element={<AuthProviderAdminPage />}
                            />
                            <Route path="*" element={<Navigate to="/admin/users" replace />} />
                        </Routes>
                    </div>
                    <Footer variant="admin" />
                </main>
            </div>

            <ConfirmModal
                open={logoutConfirmOpen}
                eyebrow={t('adminConsole.logoutEyebrow')}
                title={t('adminConsole.logoutTitle')}
                text={t('adminConsole.logoutText')}
                confirmLabel={t('common.logout')}
                cancelLabel={t('common.cancel')}
                tone="danger"
                onCancel={() => setLogoutConfirmOpen(false)}
                onConfirm={() => {
                    setLogoutConfirmOpen(false);
                    void handleLogout();
                }}
            />
        </div>
    );
}
