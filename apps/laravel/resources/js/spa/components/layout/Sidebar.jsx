import { NavLink, useLocation } from 'react-router-dom';
import { navigation } from '../../config/navigation';
import AppIcon from '../icons/AppIcon';
import { useLanguage } from '../../features/i18n/context/LanguageContext';
import { useAuth } from '../../features/auth/context/AuthContext';

/**
 * Render the primary sidebar navigation for workspace and admin entrypoints.
 */
export default function Sidebar() {
    const location = useLocation();
    const { t } = useLanguage();
    const { user } = useAuth();
    const isAdmin = user?.role === 'admin';

    const isActiveItem = (item) => {
        if (item.href === '/') {
            return location.pathname === '/';
        }

        return (item.activePrefixes ?? [item.href]).some((prefix) => {
            if (prefix === item.href) {
                return location.pathname === prefix;
            }

            return location.pathname.startsWith(prefix);
        });
    };

    return (
        <aside className="opas-sidebar" id="app-sidebar">
            <div className="opas-sidebar__scroll">
                <NavLink to={isAdmin ? '/admin/users' : '/'} className="opas-brand">
                    <span className="opas-brand__mark" aria-hidden="true">
                        <img src="/storage/images/brand/opas-logo-mark.png" alt="" />
                    </span>
                    <span className="opas-brand__copy">
                        <strong>OPAS</strong>
                        <small>
                            {t(isAdmin ? 'sidebar.adminBrand' : 'sidebar.workspaceBrand')}
                        </small>
                    </span>
                </NavLink>

                <nav className="opas-nav" aria-label={t('sidebar.primaryNavigation')}>
                    {navigation
                        .filter((section) =>
                            isAdmin
                                ? section.items.some((item) => item.adminOnly)
                                : section.items.some((item) => !item.adminOnly),
                        )
                        .map((section) => (
                            <div className="opas-nav__section" key={section.labelKey}>
                                <p className="opas-nav__label">{t(section.labelKey)}</p>
                                <div className="opas-nav__items">
                                    {section.items
                                        .filter((item) =>
                                            isAdmin ? item.adminOnly : !item.adminOnly,
                                        )
                                        .map((item) => (
                                            <NavLink
                                                key={item.href}
                                                to={item.href}
                                                end
                                                className={`opas-nav__link ${
                                                    isActiveItem(item) ? 'is-active' : ''
                                                }`}
                                            >
                                                <AppIcon name={item.icon} />
                                                <span>{t(item.labelKey)}</span>
                                            </NavLink>
                                        ))}
                                </div>
                            </div>
                        ))}
                </nav>
            </div>

            <div className="opas-sidebar__footer">
                <div className="opas-sidebar__account">
                    <span className="opas-sidebar__account-badge">
                        {(user?.name ?? 'AD').slice(0, 2).toUpperCase()}
                    </span>
                    <span className="opas-sidebar__account-copy">
                        <strong>{user?.name ?? t('header.guest')}</strong>
                        <small>{user?.role_label ?? t('header.guestHint')}</small>
                    </span>
                </div>
                <AppIcon name="chevron-down" />
            </div>
        </aside>
    );
}
