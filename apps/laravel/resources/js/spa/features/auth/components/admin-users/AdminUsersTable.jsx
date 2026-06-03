import { memo, useCallback, useMemo } from 'react';
import AppIcon, { hasAppIcon } from '../../../../components/icons/AppIcon';

/**
 * Render admin users table.
 *
 * @param {{
 *   currentUser?: Record<string, unknown>,
 *   deletingUserId: string,
 *   pagination: Record<string, number>,
 *   t: (key: string) => string,
 *   users: Array<Record<string, unknown>>,
 *   onDeleteUser: (user: Record<string, unknown>) => void,
 *   onEditUser: (userId: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AdminUsersTable({
    currentUser,
    deletingUserId,
    pagination,
    t,
    users,
    onDeleteUser,
    onEditUser,
}) {
    return (
        <div className="app-table-wrap app-table-wrap--wide">
            <table className="app-table app-user-admin-table">
                <AdminUsersTableHead t={t} />
                <tbody>
                    {users.length === 0 ? (
                        <tr>
                            <td colSpan={7} className="app-user-admin-table__empty">
                                {t('adminUsers.noResults')}
                            </td>
                        </tr>
                    ) : (
                        users.map((user, index) => (
                            <AdminUsersTableRow
                                currentUser={currentUser}
                                deletingUserId={deletingUserId}
                                index={index}
                                key={user.id}
                                pagination={pagination}
                                t={t}
                                user={user}
                                onDeleteUser={onDeleteUser}
                                onEditUser={onEditUser}
                            />
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default memo(AdminUsersTable);

const AdminUsersTableHead = memo(function AdminUsersTableHead({ t }) {
    return (
        <thead>
            <tr>
                <th className="app-user-admin-table__col-no">{t('adminUsers.columns.number')}</th>
                <th>{t('adminUsers.columns.name')}</th>
                <th>{t('adminUsers.columns.email')}</th>
                <th>{t('adminUsers.columns.role')}</th>
                <th>{t('adminUsers.columns.status')}</th>
                <th>{t('adminUsers.columns.providers')}</th>
                <th>{t('adminUsers.columns.actions')}</th>
            </tr>
        </thead>
    );
});

const AdminUsersTableRow = memo(function AdminUsersTableRow({
    currentUser,
    deletingUserId,
    index,
    pagination,
    t,
    user,
    onDeleteUser,
    onEditUser,
}) {
    const userId = String(user.id);
    const isCurrentUser =
        Boolean(user.is_current_user) || String(user.id) === String(currentUser?.id ?? '');
    const rowNumber = (pagination.from || 1) + index;
    const handleEdit = useCallback(() => {
        onEditUser(userId);
    }, [onEditUser, userId]);
    const handleDelete = useCallback(() => {
        onDeleteUser(user);
    }, [onDeleteUser, user]);
    const deleteLabel = isCurrentUser
        ? t('adminUsers.selfDeleteLocked')
        : deletingUserId === userId
          ? t('adminUsers.deleting')
          : t('adminUsers.delete');

    return (
        <tr>
            <td className="app-user-admin-table__no">{rowNumber}</td>
            <td>
                <div className="app-user-admin-table__value-block">
                    <strong className="app-table__value-strong">{user.name}</strong>
                </div>
            </td>
            <td>
                <span className="app-table__value-soft app-user-admin-table__email">
                    {user.email}
                </span>
            </td>
            <td>
                <span className="app-user-admin-table__role-badge">{user.role_label}</span>
            </td>
            <td>
                <span
                    className={`app-status-pill ${
                        user.email_verified
                            ? 'app-status-pill--success'
                            : 'app-status-pill--warning'
                    }`}
                >
                    {user.email_verified
                        ? t('adminUsers.status.verified')
                        : t('adminUsers.status.unverified')}
                </span>
            </td>
            <td className="app-user-admin-table__count">
                <AdminUsersProviderIcons providers={user.linked_providers ?? []} t={t} />
            </td>
            <td>
                <div className="app-user-admin-table__actions">
                    <button
                        type="button"
                        className="app-button app-button--ghost app-user-admin-table__action-button"
                        onClick={handleEdit}
                        title={`${t('adminUsers.edit')} ${user.name}`}
                        aria-label={`${t('adminUsers.edit')} ${user.name}`}
                    >
                        <AppIcon name="edit" />
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--danger app-user-admin-table__action-button"
                        disabled={deletingUserId === userId || isCurrentUser}
                        onClick={handleDelete}
                        title={deleteLabel}
                        aria-label={`${deleteLabel} ${user.name}`}
                        aria-busy={deletingUserId === userId}
                    >
                        <AppIcon name={deletingUserId === userId ? 'refresh' : 'trash'} />
                    </button>
                </div>
            </td>
        </tr>
    );
});

const AdminUsersProviderIcons = memo(function AdminUsersProviderIcons({ providers, t }) {
    const providerIcons = useMemo(
        () =>
            providers.map((provider) => ({
                ...provider,
                iconName: getProviderIconName(provider),
            })),
        [providers],
    );

    if (providers.length === 0) {
        return (
            <span className="app-user-admin-table__provider-empty">
                <AppIcon name="link" />
                <span>{t('adminUsers.noLinkedProviders')}</span>
            </span>
        );
    }

    return (
        <div className="app-user-admin-table__providers">
            {providerIcons.map((provider) => (
                <span
                    key={provider.key}
                    className="app-user-admin-table__provider-icon"
                    title={provider.display_name}
                    role="img"
                    aria-label={provider.display_name}
                >
                    <AppIcon name={provider.iconName} />
                </span>
            ))}
        </div>
    );
});

function getProviderIconName(provider) {
    const configuredIcon = provider.icon ?? provider.key;

    return hasAppIcon(configuredIcon) ? configuredIcon : 'link';
}
