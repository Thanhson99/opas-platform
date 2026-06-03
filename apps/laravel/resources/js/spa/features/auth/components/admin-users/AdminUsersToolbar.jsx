import { memo, useCallback } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render admin user search controls and result range.
 *
 * @param {{
 *   activeSearch: string,
 *   pagination: Record<string, number>,
 *   searchInput: string,
 *   t: (key: string) => string,
 *   onClearSearch: () => void,
 *   onSearchChange: (value: string) => void,
 *   onSearchSubmit: (event: import('react').FormEvent<HTMLFormElement>) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AdminUsersToolbar({
    activeSearch,
    pagination,
    searchInput,
    t,
    onClearSearch,
    onSearchChange,
    onSearchSubmit,
}) {
    const handleSearchChange = useCallback(
        (event) => {
            onSearchChange(event.target.value);
        },
        [onSearchChange],
    );

    return (
        <form className="app-search-row app-user-admin-toolbar" onSubmit={onSearchSubmit}>
            <div className="app-user-admin-toolbar__search">
                <label className="app-visually-hidden" htmlFor="admin-users-search">
                    {t('adminUsers.searchPlaceholder')}
                </label>
                <AppIcon name="search" />
                <input
                    id="admin-users-search"
                    type="search"
                    className="app-input"
                    value={searchInput}
                    onChange={handleSearchChange}
                    placeholder={t('adminUsers.searchPlaceholder')}
                />
            </div>
            <button
                type="submit"
                className="app-button app-button--primary"
                title={t('adminUsers.searchButton')}
            >
                <AppIcon name="search" />
                {t('adminUsers.searchButton')}
            </button>
            <button
                type="button"
                className="app-button app-button--ghost"
                onClick={onClearSearch}
                disabled={!searchInput && !activeSearch}
                title={t('adminUsers.clearSearch')}
            >
                <AppIcon name="x" />
                {t('adminUsers.clearSearch')}
            </button>
            <div className="app-user-admin-toolbar__summary">
                {`${t('adminUsers.showingRange')}: ${pagination.from}-${pagination.to} / ${pagination.total}`}
            </div>
        </form>
    );
}

export default memo(AdminUsersToolbar);
