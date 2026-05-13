import { useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import Sidebar from './Sidebar';
import Header from './Header';
import Footer from './Footer';
import { useLanguage } from '../../features/i18n/context/LanguageContext';

/**
 * Main application shell for the React SPA.
 *
 * Keeps layout concerns in one place: sidebar, sticky header,
 * footer, and mobile sidebar state.
 */
export default function AppShell({ children }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const location = useLocation();
    const { t } = useLanguage();

    const title = useMemo(() => {
        if (location.pathname === '/') {
            return {
                title: t('shell.dashboardTitle'),
                description: t('shell.dashboardDescription'),
            };
        }

        if (location.pathname.startsWith('/coins/show/')) {
            return {
                title: t('shell.coinDetailTitle'),
                description: t('shell.coinDetailDescription'),
            };
        }

        if (location.pathname.startsWith('/coins/feed-keywords')) {
            return {
                title: t('shell.keywordsTitle'),
                description: t('shell.keywordsDescription'),
            };
        }

        if (location.pathname.startsWith('/coins/price-alert-settings')) {
            return {
                title: t('shell.alertsTitle'),
                description: t('shell.alertsDescription'),
            };
        }

        if (location.pathname.startsWith('/coins')) {
            return {
                title: t('shell.coinsTitle'),
                description: t('shell.coinsDescription'),
            };
        }

        if (location.pathname.startsWith('/stocks')) {
            return {
                title: t('shell.stocksTitle'),
                description: t('shell.stocksDescription'),
            };
        }

        if (location.pathname.startsWith('/video-automation/trending')) {
            return {
                title: t('shell.videosTitle'),
                description: t('shell.videosDescription'),
            };
        }

        return {
            title: t('shell.fallbackTitle'),
            description: t('shell.fallbackDescription'),
        };
    }, [location.pathname, t]);

    return (
        <div className={`opas-app ${sidebarOpen ? 'is-sidebar-open' : ''}`} id="app-shell">
            <Sidebar />
            <div className="opas-main">
                <Header
                    title={title.title}
                    description={title.description}
                    onToggleSidebar={() => setSidebarOpen((value) => !value)}
                />
                <main className="opas-content">{children}</main>
                <Footer />
            </div>
        </div>
    );
}
