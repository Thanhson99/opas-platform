import { useAuth } from '../../features/auth/context/AuthContext';
import { useLanguage } from '../../features/i18n/context/LanguageContext';
import { HeaderActions, HeaderIntro } from './header/index';

/**
 * Render the workspace header with account actions and route-specific titles.
 *
 * @param {{
 *   title: string,
 *   description?: string,
 *   sidebarOpen?: boolean,
 *   onToggleSidebar: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function Header({ title, description, sidebarOpen = false, onToggleSidebar }) {
    const { user, loading, logout, registerProviders } = useAuth();
    const { t } = useLanguage();

    return (
        <header className="opas-header">
            <div className="opas-header__main">
                <HeaderIntro
                    title={title}
                    description={description}
                    sidebarOpen={sidebarOpen}
                    onToggleSidebar={onToggleSidebar}
                    t={t}
                />
                <HeaderActions
                    loading={loading}
                    user={user}
                    registerProviders={registerProviders}
                    logout={logout}
                    t={t}
                />
            </div>
        </header>
    );
}
