import { memo } from 'react';
import { NavLink } from 'react-router-dom';
import AppIcon, { hasAppIcon } from '../icons/AppIcon';

/**
 * Render the OPAS admin sidebar navigation.
 *
 * @param {{
 *   authMenuOpen: boolean,
 *   authProviderDetailActive: boolean,
 *   authProvidersDashboardActive: boolean,
 *   authProviderMenuItems: {href: string, label: string, icon: string}[],
 *   currentPath: string,
 *   t: (key: string) => string,
 *   onAuthMenuToggle: () => void,
 *   onAuthMenuLinkClick: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AdminSidebar({
    authMenuOpen,
    authProviderDetailActive,
    authProvidersDashboardActive,
    authProviderMenuItems,
    currentPath,
    t,
    onAuthMenuToggle,
    onAuthMenuLinkClick,
}) {
    return (
        <aside className="admin-console__sidebar">
            <div className="admin-console__sidebar-scroll">
                <NavLink to="/admin/users" className="admin-console__brand">
                    <span className="admin-console__brand-mark" aria-hidden="true">
                        <AppIcon name="trend-up" />
                    </span>
                    <span className="admin-console__brand-copy">
                        <strong>{t('adminConsole.brandTitle')}</strong>
                        <small>{t('adminConsole.brandText')}</small>
                    </span>
                </NavLink>

                <nav className="admin-console__nav">
                    <div className="admin-console__nav-section">
                        <span className="admin-console__nav-label">
                            {t('adminConsole.nav.management')}
                        </span>
                        <div className="admin-console__nav-items">
                            <NavLink
                                to="/admin/users"
                                className={({ isActive }) =>
                                    `admin-console__nav-link ${isActive ? 'is-active' : ''}`
                                }
                            >
                                <AppIcon name="users" />
                                <span>{t('adminUsers.menuLabel')}</span>
                            </NavLink>
                            <NavLink
                                to="/admin/auto-coding/observability"
                                className={({ isActive }) =>
                                    `admin-console__nav-link ${isActive ? 'is-active' : ''}`
                                }
                            >
                                <AppIcon name="activity" />
                                <span>{t('adminAutoCodingOps.menuLabel')}</span>
                            </NavLink>
                            <NavLink
                                to="/admin/auto-coding/telegram-bots"
                                className={({ isActive }) =>
                                    `admin-console__nav-link ${isActive ? 'is-active' : ''}`
                                }
                            >
                                <AppIcon name="bot" />
                                <span>{t('adminTelegramBots.menuLabel')}</span>
                            </NavLink>
                        </div>
                    </div>

                    <div className="admin-console__nav-section">
                        <span className="admin-console__nav-label">
                            {t('adminConsole.nav.system')}
                        </span>
                        <div className="admin-console__nav-group">
                            <div
                                className={`admin-console__nav-parent ${
                                    authProvidersDashboardActive ? 'is-active' : ''
                                } ${authMenuOpen ? 'is-open' : ''}`}
                            >
                                <NavLink
                                    to="/admin/auth/providers"
                                    className="admin-console__nav-link admin-console__nav-link--parent"
                                    onClick={onAuthMenuLinkClick}
                                >
                                    <AppIcon name="shield" />
                                    <span>{t('nav.authProviders')}</span>
                                </NavLink>
                                <button
                                    type="button"
                                    className={`admin-console__nav-toggle ${
                                        authMenuOpen ? 'is-open' : ''
                                    }`}
                                    onClick={onAuthMenuToggle}
                                    aria-expanded={authMenuOpen}
                                    aria-label={t('adminAuth.menu.toggle')}
                                    title={t('adminAuth.menu.toggle')}
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
                                                `admin-console__subnav-link ${
                                                    isActive ||
                                                    (authProviderDetailActive &&
                                                        currentPath === item.href)
                                                        ? 'is-active'
                                                        : ''
                                                }`
                                            }
                                        >
                                            <AppIcon
                                                name={hasAppIcon(item.icon) ? item.icon : 'shield'}
                                            />
                                            <span>{item.label}</span>
                                        </NavLink>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    </div>
                </nav>
            </div>
        </aside>
    );
}

export default memo(AdminSidebar);
