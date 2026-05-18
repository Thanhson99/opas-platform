import { useCallback, useEffect, useMemo, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import AdminUserEditModal from './AdminUserEditModal';
import ConfirmModal from '../../../../components/ui/ConfirmModal';
import ErrorState from '../../../../components/ui/ErrorState';
import LoadingState from '../../../../components/ui/LoadingState';
import PageHero from '../../../../components/ui/PageHero';
import api from '../../../../lib/api';
import { useLanguage } from '../../../i18n/context/LanguageContext';

const PER_PAGE = 10;
const EMPTY_PAGINATION = {
    currentPage: 1,
    lastPage: 1,
    perPage: PER_PAGE,
    total: 0,
    from: 0,
    to: 0,
};

function buildUserForms(users) {
    return Object.fromEntries(
        users.map((user) => [
            String(user.id),
            {
                name: user.name ?? '',
                email: user.email ?? '',
                role: user.role ?? 'member',
            },
        ]),
    );
}

function firstErrorMessage(requestError, fallbackMessage) {
    const errors = requestError?.response?.data?.errors;

    if (errors && typeof errors === 'object') {
        const firstField = Object.values(errors)[0];

        if (Array.isArray(firstField) && firstField[0]) {
            return firstField[0];
        }
    }

    return requestError?.response?.data?.message || fallbackMessage;
}

function hasRowChanged(form, initialForm) {
    if (!form || !initialForm) {
        return false;
    }

    return form.name.trim() !== initialForm.name.trim() || form.role !== initialForm.role;
}

function isRowSubmittable(form) {
    return Boolean(form?.name?.trim()) && Boolean(form?.role);
}

function buildPaginationItems(currentPage, lastPage) {
    if (lastPage <= 1) {
        return [1];
    }

    const pages = new Set([1, lastPage, currentPage, currentPage - 1, currentPage + 1]);

    return Array.from(pages)
        .filter((page) => page >= 1 && page <= lastPage)
        .sort((left, right) => left - right);
}

export default function AdminUsersPage() {
    const { t } = useLanguage();
    const { user: currentUser } = useAuth();
    const [users, setUsers] = useState([]);
    const [forms, setForms] = useState({});
    const [initialForms, setInitialForms] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [flash, setFlash] = useState('');
    const [savingUserId, setSavingUserId] = useState('');
    const [deletingUserId, setDeletingUserId] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});
    const [confirmDeleteUser, setConfirmDeleteUser] = useState(null);
    const [confirmResetUser, setConfirmResetUser] = useState(null);
    const [editingUserId, setEditingUserId] = useState('');
    const [resettingUserId, setResettingUserId] = useState('');
    const [searchInput, setSearchInput] = useState('');
    const [activeSearch, setActiveSearch] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const [pagination, setPagination] = useState(EMPTY_PAGINATION);

    const loadUsers = useCallback(
        async (pageNumber, searchTerm) => {
            setLoading(true);

            try {
                const response = await api.get('/admin/users', {
                    params: {
                        page: pageNumber,
                        per_page: PER_PAGE,
                        search: searchTerm || undefined,
                    },
                });
                const nextUsers = response.data.data ?? [];
                const nextForms = buildUserForms(nextUsers);
                const meta = response.data.meta ?? {};

                setUsers(nextUsers);
                setForms(nextForms);
                setInitialForms(nextForms);
                setFieldErrors({});
                setError('');
                setEditingUserId('');
                setPagination({
                    currentPage: meta.current_page ?? 1,
                    lastPage: meta.last_page ?? 1,
                    perPage: meta.per_page ?? PER_PAGE,
                    total: meta.total ?? nextUsers.length,
                    from: meta.from ?? 0,
                    to: meta.to ?? nextUsers.length,
                });
            } catch (requestError) {
                setUsers([]);
                setError(requestError?.response?.data?.message || t('adminUsers.loadError'));
                setPagination(EMPTY_PAGINATION);
            } finally {
                setLoading(false);
            }
        },
        [t],
    );

    useEffect(() => {
        void loadUsers(currentPage, activeSearch);
    }, [activeSearch, currentPage, loadUsers]);

    const summary = useMemo(
        () => ({
            total: pagination.total,
            page: pagination.currentPage,
            visible: users.length,
        }),
        [pagination, users.length],
    );

    const paginationItems = useMemo(
        () => buildPaginationItems(pagination.currentPage, pagination.lastPage),
        [pagination.currentPage, pagination.lastPage],
    );

    if (loading && users.length === 0) {
        return <LoadingState text={t('adminUsers.loading')} />;
    }

    if (error && users.length === 0) {
        return <ErrorState text={error} />;
    }

    const saveUser = async (userId) => {
        const form = forms[userId];
        const initialForm = initialForms[userId];

        if (!isRowSubmittable(form) || !hasRowChanged(form, initialForm)) {
            return;
        }

        setSavingUserId(userId);
        setFlash('');
        setFieldErrors((current) => ({
            ...current,
            [userId]: '',
        }));

        try {
            const response = await api.put(`/admin/users/${userId}`, {
                name: form.name.trim(),
                role: form.role,
            });
            const nextUser = response.data.data;

            setFlash(`${t('adminUsers.savedPrefix')} ${nextUser.email}.`);
            setEditingUserId('');
            await loadUsers(currentPage, activeSearch);
        } catch (requestError) {
            setFieldErrors((current) => ({
                ...current,
                [userId]: firstErrorMessage(requestError, t('adminUsers.saveError')),
            }));
        } finally {
            setSavingUserId('');
        }
    };

    const deleteUser = async () => {
        if (!confirmDeleteUser) {
            return;
        }

        const userId = String(confirmDeleteUser.id);

        setDeletingUserId(userId);
        setFlash('');
        setFieldErrors((current) => ({
            ...current,
            [userId]: '',
        }));

        try {
            await api.delete(`/admin/users/${userId}`);

            const targetPage =
                users.length === 1 && currentPage > 1 ? currentPage - 1 : currentPage;

            setFlash(`${t('adminUsers.deletedPrefix')} ${confirmDeleteUser.email}.`);
            setConfirmDeleteUser(null);

            if (targetPage !== currentPage) {
                setCurrentPage(targetPage);
            } else {
                await loadUsers(targetPage, activeSearch);
            }
        } catch (requestError) {
            setFieldErrors((current) => ({
                ...current,
                [userId]: firstErrorMessage(requestError, t('adminUsers.deleteError')),
            }));
            setConfirmDeleteUser(null);
        } finally {
            setDeletingUserId('');
        }
    };

    const resetPassword = async (userId) => {
        setResettingUserId(userId);
        setFlash('');
        setFieldErrors((current) => ({
            ...current,
            [userId]: '',
        }));

        try {
            const response = await api.post(`/admin/users/${userId}/reset-password`);
            setFlash(response.data.message || t('adminUsers.resetPasswordSuccess'));
        } catch (requestError) {
            setFieldErrors((current) => ({
                ...current,
                [userId]: firstErrorMessage(requestError, t('adminUsers.resetPasswordError')),
            }));
        } finally {
            setResettingUserId('');
        }
    };

    const confirmResetPassword = async () => {
        if (!confirmResetUser) {
            return;
        }

        const userId = String(confirmResetUser.id);

        try {
            await resetPassword(userId);
        } finally {
            setConfirmResetUser(null);
        }
    };

    const handleSearchSubmit = (event) => {
        event.preventDefault();
        setFlash('');
        setCurrentPage(1);
        setActiveSearch(searchInput.trim());
    };

    const handleSearchReset = () => {
        setSearchInput('');
        setFlash('');
        setCurrentPage(1);
        setActiveSearch('');
    };

    const startEditing = (userId) => {
        setFlash('');
        setFieldErrors((current) => ({
            ...current,
            [userId]: '',
        }));
        setEditingUserId(userId);
    };

    const cancelEditing = (userId) => {
        setForms((current) => ({
            ...current,
            [userId]: {
                ...(initialForms[userId] ?? current[userId]),
            },
        }));
        setFieldErrors((current) => ({
            ...current,
            [userId]: '',
        }));
        setEditingUserId('');
    };

    const editingUser = users.find((user) => String(user.id) === editingUserId) ?? null;
    const editingForm = editingUserId ? (forms[editingUserId] ?? null) : null;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow={t('adminUsers.hero.eyebrow')}
                title={t('adminUsers.hero.title')}
                text={t('adminUsers.hero.text')}
            >
                <span className="app-chip">
                    {t('adminUsers.summary.total')} {summary.total}
                </span>
                <span className="app-chip">
                    {t('adminUsers.summary.page')} {summary.page}
                </span>
                <span className="app-chip">
                    {t('adminUsers.summary.visible')} {summary.visible}
                </span>
            </PageHero>

            {flash ? (
                <div className="app-provider-note app-provider-note--success">{flash}</div>
            ) : null}
            {error ? (
                <div className="app-provider-note app-provider-note--warning">{error}</div>
            ) : null}

            <section className="app-list-card">
                <form
                    className="app-search-row app-user-admin-toolbar"
                    onSubmit={handleSearchSubmit}
                >
                    <div className="app-user-admin-toolbar__search">
                        <input
                            type="search"
                            className="app-input"
                            value={searchInput}
                            onChange={(event) => setSearchInput(event.target.value)}
                            placeholder={t('adminUsers.searchPlaceholder')}
                        />
                    </div>
                    <button type="submit" className="app-button app-button--primary">
                        {t('adminUsers.searchButton')}
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={handleSearchReset}
                        disabled={!searchInput && !activeSearch}
                    >
                        {t('adminUsers.clearSearch')}
                    </button>
                    <div className="app-user-admin-toolbar__summary">
                        {`${t('adminUsers.showingRange')}: ${pagination.from}-${pagination.to} / ${pagination.total}`}
                    </div>
                </form>

                <div className="app-table-wrap app-table-wrap--wide">
                    <table className="app-table app-user-admin-table">
                        <thead>
                            <tr>
                                <th className="app-user-admin-table__col-no">
                                    {t('adminUsers.columns.number')}
                                </th>
                                <th>{t('adminUsers.columns.name')}</th>
                                <th>{t('adminUsers.columns.email')}</th>
                                <th>{t('adminUsers.columns.role')}</th>
                                <th>{t('adminUsers.columns.status')}</th>
                                <th>{t('adminUsers.columns.providers')}</th>
                                <th>{t('adminUsers.columns.actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="app-user-admin-table__empty">
                                        {t('adminUsers.noResults')}
                                    </td>
                                </tr>
                            ) : (
                                users.map((user, index) => {
                                    const userId = String(user.id);
                                    const isCurrentUser =
                                        Boolean(user.is_current_user) ||
                                        String(user.id) === String(currentUser?.id ?? '');
                                    const rowNumber = (pagination.from || 1) + index;

                                    return (
                                        <tr key={user.id}>
                                            <td className="app-user-admin-table__no">
                                                {rowNumber}
                                            </td>
                                            <td>
                                                <div className="app-user-admin-table__value-block">
                                                    <strong className="app-table__value-strong">
                                                        {user.name}
                                                    </strong>
                                                </div>
                                            </td>
                                            <td>
                                                <span className="app-table__value-soft app-user-admin-table__email">
                                                    {user.email}
                                                </span>
                                            </td>
                                            <td>
                                                <span className="app-user-admin-table__role-badge">
                                                    {user.role_label}
                                                </span>
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
                                                {user.auth_identity_count}
                                            </td>
                                            <td>
                                                <div className="app-user-admin-table__actions">
                                                    <button
                                                        type="button"
                                                        className="app-button app-button--ghost"
                                                        onClick={() => startEditing(userId)}
                                                    >
                                                        {t('adminUsers.edit')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="app-button app-button--danger"
                                                        disabled={
                                                            deletingUserId === userId ||
                                                            isCurrentUser
                                                        }
                                                        onClick={() => setConfirmDeleteUser(user)}
                                                    >
                                                        {deletingUserId === userId
                                                            ? t('adminUsers.deleting')
                                                            : t('adminUsers.delete')}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="app-user-admin-pagination">
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        disabled={pagination.currentPage <= 1}
                        onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                    >
                        {t('adminUsers.pagination.previous')}
                    </button>

                    <div className="app-user-admin-pagination__pages">
                        {paginationItems.map((page) => (
                            <button
                                key={page}
                                type="button"
                                className={`app-user-admin-pagination__page ${
                                    page === pagination.currentPage ? 'is-active' : ''
                                }`}
                                onClick={() => setCurrentPage(page)}
                            >
                                {page}
                            </button>
                        ))}
                    </div>

                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        disabled={pagination.currentPage >= pagination.lastPage}
                        onClick={() =>
                            setCurrentPage((page) => Math.min(pagination.lastPage, page + 1))
                        }
                    >
                        {t('adminUsers.pagination.next')}
                    </button>
                </div>

                <p className="app-field__help app-user-admin-table__help">
                    {t('adminUsers.roleHelp')}
                </p>
            </section>

            <AdminUserEditModal
                open={Boolean(editingUser && editingForm)}
                t={t}
                user={editingUser}
                form={editingForm}
                error={editingUserId ? fieldErrors[editingUserId] : ''}
                saving={Boolean(editingUserId) && savingUserId === editingUserId}
                resetting={Boolean(editingUserId) && resettingUserId === editingUserId}
                onChange={(field, value) => {
                    if (!editingUserId) {
                        return;
                    }

                    setForms((current) => ({
                        ...current,
                        [editingUserId]: {
                            ...(current[editingUserId] ?? editingForm ?? {}),
                            [field]: value,
                        },
                    }));
                }}
                onCancel={() => {
                    if (editingUserId) {
                        cancelEditing(editingUserId);
                    }
                }}
                onSave={() => {
                    if (editingUserId) {
                        void saveUser(editingUserId);
                    }
                }}
                onResetPassword={() => {
                    if (editingUserId) {
                        setConfirmResetUser(editingUser);
                    }
                }}
            />

            <ConfirmModal
                open={Boolean(confirmDeleteUser)}
                eyebrow={t('adminUsers.deleteModal.eyebrow')}
                title={t('adminUsers.deleteModal.title')}
                text={
                    confirmDeleteUser
                        ? `${t('adminUsers.deleteModal.text')} ${confirmDeleteUser.email}`
                        : ''
                }
                confirmLabel={t('adminUsers.deleteModal.confirm')}
                cancelLabel={t('common.cancel')}
                tone="danger"
                onCancel={() => setConfirmDeleteUser(null)}
                onConfirm={() => {
                    void deleteUser();
                }}
            />

            <ConfirmModal
                open={Boolean(confirmResetUser)}
                eyebrow={t('adminUsers.resetModal.eyebrow')}
                title={t('adminUsers.resetModal.title')}
                text={
                    confirmResetUser
                        ? `${t('adminUsers.resetModal.text')} ${confirmResetUser.email}`
                        : ''
                }
                confirmLabel={t('adminUsers.resetModal.confirm')}
                cancelLabel={t('common.cancel')}
                tone="danger"
                onCancel={() => setConfirmResetUser(null)}
                onConfirm={() => {
                    void confirmResetPassword();
                }}
            />
        </div>
    );
}
