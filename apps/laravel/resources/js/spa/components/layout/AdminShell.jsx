import { useCallback, useEffect, useMemo, useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import ConfirmModal from '../ui/ConfirmModal';
import api from '../../lib/api';
import { useAuth } from '../../features/auth/context/AuthContext';
import { useLanguage } from '../../features/i18n/context/LanguageContext';
import AdminRoutes from './AdminRoutes';
import AdminSidebar from './AdminSidebar';
import AdminTopbar from './AdminTopbar';
import Footer from './Footer';
import '../../../../scss/modules/_cyberpunk-system.scss';
import '../../../../scss/modules/_admin-auth-providers.scss';
import '../../../../scss/modules/_admin-console.scss';

let cachedAdminProviderMenuItems = null;
let pendingAdminProviderMenuRequest = null;

function isAuthProvidersDashboardPath(pathname) {
    return pathname === '/admin/auth/providers';
}

function isAuthProviderDetailPath(pathname) {
    return pathname.startsWith('/admin/auth/providers/');
}

function resolveAdminPageLabel(pathname, t) {
    if (pathname.startsWith('/admin/auto-coding/telegram-bots')) {
        return t('adminTelegramBots.menuLabel');
    }

    if (pathname.startsWith('/admin/users')) {
        return t('adminUsers.menuLabel');
    }

    if (pathname.startsWith('/admin/auth/providers')) {
        return t('nav.authProviders');
    }

    return t('adminConsole.sectionLabel');
}

async function loadAdminProviderMenuItems() {
    if (cachedAdminProviderMenuItems) {
        return cachedAdminProviderMenuItems;
    }

    if (pendingAdminProviderMenuRequest) {
        return pendingAdminProviderMenuRequest;
    }

    pendingAdminProviderMenuRequest = api
        .get('/admin/auth/providers')
        .then((response) => {
            const providers = response.data.data ?? [];

            cachedAdminProviderMenuItems = providers.map((provider) => ({
                href: `/admin/auth/providers/${provider.key}`,
                label: provider.display_name,
                icon: provider.icon,
            }));

            return cachedAdminProviderMenuItems;
        })
        .finally(() => {
            pendingAdminProviderMenuRequest = null;
        });

    return pendingAdminProviderMenuRequest;
}

function scheduleAdminProviderMenuLoad(callback) {
    if (typeof window === 'undefined') {
        callback();

        return () => {};
    }

    if ('requestIdleCallback' in window) {
        const handle = window.requestIdleCallback(callback, { timeout: 1400 });

        return () => window.cancelIdleCallback(handle);
    }

    const handle = window.setTimeout(callback, 250);

    return () => window.clearTimeout(handle);
}

/**
 * Render the admin console shell, navigation, and admin-only route tree.
 *
 * @returns {import('react').JSX.Element}
 */
export default function AdminShell() {
    const navigate = useNavigate();
    const location = useLocation();
    const { user, loading, logout } = useAuth();
    const { t } = useLanguage();
    const [logoutConfirmOpen, setLogoutConfirmOpen] = useState(false);
    const [authMenuOpen, setAuthMenuOpen] = useState(false);
    const [authProviderMenuItems, setAuthProviderMenuItems] = useState([]);

    useEffect(() => {
        if (loading || user?.role !== 'admin') {
            return undefined;
        }

        const loadMenu = () => {
            void loadAdminProviderMenuItems()
                .then(setAuthProviderMenuItems)
                .catch(() => setAuthProviderMenuItems([]));
        };

        if (
            isAuthProvidersDashboardPath(location.pathname) ||
            isAuthProviderDetailPath(location.pathname)
        ) {
            loadMenu();

            return undefined;
        }

        return scheduleAdminProviderMenuLoad(loadMenu);
    }, [loading, location.pathname, user?.role]);

    useEffect(() => {
        if (isAuthProviderDetailPath(location.pathname)) {
            setAuthMenuOpen(true);

            return;
        }

        if (!isAuthProvidersDashboardPath(location.pathname)) {
            setAuthMenuOpen(false);
        }
    }, [location.pathname]);

    const handleLogout = useCallback(async () => {
        try {
            await logout();
        } finally {
            navigate('/login', { replace: true });
        }
    }, [logout, navigate]);

    const authProvidersDashboardActive = isAuthProvidersDashboardPath(location.pathname);
    const authProviderDetailActive = isAuthProviderDetailPath(location.pathname);
    const currentPageLabel = useMemo(
        () => resolveAdminPageLabel(location.pathname, t),
        [location.pathname, t],
    );
    const handleAuthMenuToggle = useCallback(() => {
        setAuthMenuOpen((value) => !value);
    }, []);
    const handleAuthMenuLinkClick = useCallback(() => {
        setAuthMenuOpen((value) => (authProvidersDashboardActive ? !value : true));
    }, [authProvidersDashboardActive]);
    const handleLogoutClick = useCallback(() => {
        setLogoutConfirmOpen(true);
    }, []);
    const handleLogoutCancel = useCallback(() => {
        setLogoutConfirmOpen(false);
    }, []);
    const handleLogoutConfirm = useCallback(() => {
        setLogoutConfirmOpen(false);
        void handleLogout();
    }, [handleLogout]);

    if (!loading && user?.role !== 'admin') {
        return <Navigate to="/login" replace />;
    }

    return (
        <div className="admin-console">
            <AdminSidebar
                authMenuOpen={authMenuOpen}
                authProviderDetailActive={authProviderDetailActive}
                authProvidersDashboardActive={authProvidersDashboardActive}
                authProviderMenuItems={authProviderMenuItems}
                currentPath={location.pathname}
                t={t}
                onAuthMenuToggle={handleAuthMenuToggle}
                onAuthMenuLinkClick={handleAuthMenuLinkClick}
            />

            <main className="admin-console__main">
                <AdminTopbar
                    currentPageLabel={currentPageLabel}
                    user={user}
                    t={t}
                    onLogoutClick={handleLogoutClick}
                />
                <div className="admin-console__content">
                    <div className="admin-console__content-main">
                        <AdminRoutes />
                    </div>
                    <Footer variant="admin" />
                </div>
            </main>

            <ConfirmModal
                open={logoutConfirmOpen}
                eyebrow={t('adminConsole.logoutEyebrow')}
                title={t('adminConsole.logoutTitle')}
                text={t('adminConsole.logoutText')}
                confirmLabel={t('common.logout')}
                cancelLabel={t('common.cancel')}
                tone="danger"
                onCancel={handleLogoutCancel}
                onConfirm={handleLogoutConfirm}
            />
        </div>
    );
}
