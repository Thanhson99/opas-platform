import { memo } from 'react';
import AdminUsersPagination from './AdminUsersPagination';
import AdminUsersTable from './AdminUsersTable';
import AdminUsersToolbar from './AdminUsersToolbar';

/**
 * Render admin user search, table, and pagination.
 *
 * @param {{
 *   activeSearch: string,
 *   currentUser?: Record<string, unknown>,
 *   deletingUserId: string,
 *   pagination: Record<string, number>,
 *   paginationItems: Array<number>,
 *   searchInput: string,
 *   t: (key: string) => string,
 *   users: Array<Record<string, unknown>>,
 *   onClearSearch: () => void,
 *   onDeleteUser: (user: Record<string, unknown>) => void,
 *   onEditUser: (userId: string) => void,
 *   onPageChange: (updater: number|((page: number) => number)) => void,
 *   onSearchChange: (value: string) => void,
 *   onSearchSubmit: (event: import('react').FormEvent<HTMLFormElement>) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AdminUsersListSection({
    activeSearch,
    currentUser,
    deletingUserId,
    pagination,
    paginationItems,
    searchInput,
    t,
    users,
    onClearSearch,
    onDeleteUser,
    onEditUser,
    onPageChange,
    onSearchChange,
    onSearchSubmit,
}) {
    return (
        <section className="app-list-card">
            <AdminUsersToolbar
                activeSearch={activeSearch}
                pagination={pagination}
                searchInput={searchInput}
                t={t}
                onClearSearch={onClearSearch}
                onSearchChange={onSearchChange}
                onSearchSubmit={onSearchSubmit}
            />

            <AdminUsersTable
                currentUser={currentUser}
                deletingUserId={deletingUserId}
                pagination={pagination}
                t={t}
                users={users}
                onDeleteUser={onDeleteUser}
                onEditUser={onEditUser}
            />

            <AdminUsersPagination
                pagination={pagination}
                paginationItems={paginationItems}
                t={t}
                onPageChange={onPageChange}
            />

            <p className="app-field__help app-user-admin-table__help">{t('adminUsers.roleHelp')}</p>
        </section>
    );
}

export default memo(AdminUsersListSection);
