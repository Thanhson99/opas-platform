import { memo, useCallback, useId, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import AppIcon from '../icons/AppIcon';
import LanguageSelect from './LanguageSelect';

/**
 * Render the admin shell topbar.
 *
 * @param {{
 *   currentPageLabel: string,
 *   user: Record<string, any> | null,
 *   t: (key: string) => string,
 *   onLogoutClick: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AdminTopbar({ currentPageLabel, user, t, onLogoutClick }) {
    const navigate = useNavigate();
    const searchMenuId = useId();
    const [searchValue, setSearchValue] = useState('');
    const [searchFocused, setSearchFocused] = useState(false);
    const initials = (user?.name ?? 'AD').slice(0, 2).toUpperCase();
    const adminModules = useMemo(
        () => [
            {
                href: '/admin/users',
                icon: 'users',
                label: t('adminUsers.menuLabel'),
                meta: t('adminConsole.nav.management'),
            },
            {
                href: '/admin/auto-coding/telegram-bots',
                icon: 'bot',
                label: t('adminTelegramBots.menuLabel'),
                meta: t('adminConsole.nav.management'),
            },
            {
                href: '/admin/auth/providers',
                icon: 'shield',
                label: t('nav.authProviders'),
                meta: t('adminConsole.nav.system'),
            },
        ],
        [t],
    );
    const visibleModules = useMemo(() => {
        const normalizedSearch = searchValue.trim().toLowerCase();

        if (!normalizedSearch) {
            return adminModules;
        }

        return adminModules.filter(
            (item) =>
                item.label.toLowerCase().includes(normalizedSearch) ||
                item.meta.toLowerCase().includes(normalizedSearch),
        );
    }, [adminModules, searchValue]);
    const hasSearchQuery = searchValue.trim() !== '';
    const searchOpen = searchFocused && (visibleModules.length > 0 || hasSearchQuery);

    const navigateToModule = useCallback(
        (href) => {
            setSearchValue('');
            setSearchFocused(false);
            navigate(href);
        },
        [navigate],
    );

    const handleSearchSubmit = useCallback(
        (event) => {
            event.preventDefault();

            if (visibleModules[0]) {
                navigateToModule(visibleModules[0].href);
            }
        },
        [navigateToModule, visibleModules],
    );
    const handleSearchBlur = useCallback(() => {
        setSearchFocused(false);
    }, []);
    const handleSearchChange = useCallback((event) => {
        const nextValue = event.target.value;

        setSearchValue((currentValue) => (currentValue === nextValue ? currentValue : nextValue));
    }, []);
    const handleSearchFocus = useCallback(() => {
        setSearchFocused(true);
    }, []);
    const handleSearchKeyDown = useCallback((event) => {
        if (event.key === 'Escape') {
            setSearchFocused(false);
            event.currentTarget.blur();
        }
    }, []);
    const handleSearchOptionMouseDown = useCallback(
        (event) => {
            event.preventDefault();
            navigateToModule(event.currentTarget.dataset.href);
        },
        [navigateToModule],
    );

    return (
        <div className="admin-console__topbar">
            <div>
                <div className="admin-console__breadcrumbs">
                    <span>{t('common.home')}</span>
                    <AppIcon name="chevron-down" className="admin-console__crumb-icon" />
                    <strong>{currentPageLabel}</strong>
                </div>
                <p className="admin-console__topbar-subtitle">{t('adminConsole.brandText')}</p>
            </div>

            <div className="admin-console__actions">
                <form
                    className="admin-console__search"
                    role="search"
                    autoComplete="off"
                    onSubmit={handleSearchSubmit}
                >
                    <AppIcon name="search" />
                    <input
                        id="admin-global-search"
                        type="search"
                        value={searchValue}
                        placeholder={t('adminConsole.searchPlaceholder')}
                        className="admin-console__search-input"
                        aria-controls={searchOpen ? searchMenuId : undefined}
                        aria-expanded={searchOpen}
                        onBlur={handleSearchBlur}
                        onChange={handleSearchChange}
                        onFocus={handleSearchFocus}
                        onKeyDown={handleSearchKeyDown}
                    />
                    {searchOpen ? (
                        <div
                            id={searchMenuId}
                            className="admin-console__search-menu"
                            role="listbox"
                        >
                            {visibleModules.length > 0 ? (
                                visibleModules.map((item) => (
                                    <button
                                        key={item.href}
                                        type="button"
                                        className="admin-console__search-option"
                                        data-href={item.href}
                                        role="option"
                                        aria-selected="false"
                                        onMouseDown={handleSearchOptionMouseDown}
                                    >
                                        <AppIcon name={item.icon} />
                                        <span>
                                            <strong>{item.label}</strong>
                                            <small>{item.meta}</small>
                                        </span>
                                    </button>
                                ))
                            ) : (
                                <div className="admin-console__search-empty" role="status">
                                    {t('adminConsole.searchNoResults')}
                                </div>
                            )}
                        </div>
                    ) : null}
                </form>
                <button
                    type="button"
                    className="admin-console__icon-button"
                    aria-label={t('adminConsole.alertsButton')}
                    title={t('adminConsole.alertsButton')}
                >
                    <AppIcon name="alerts" />
                </button>
                <LanguageSelect />
                <div className="admin-console__account">
                    <span className="admin-console__account-badge">{initials}</span>
                    <div>
                        <strong>{user?.name ?? t('header.guest')}</strong>
                        <span>{user?.email ?? ''}</span>
                    </div>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        aria-label={t('common.logout')}
                        onClick={onLogoutClick}
                    >
                        <AppIcon name="logout" />
                        {t('common.logout')}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default memo(AdminTopbar);
