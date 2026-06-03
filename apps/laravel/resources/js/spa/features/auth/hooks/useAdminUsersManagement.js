import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    deleteAdminUser,
    getAdminUsers,
    resetAdminUserPassword,
    updateAdminUser,
} from '../services/auth.service';
import {
    ADMIN_USERS_PER_PAGE,
    EMPTY_ADMIN_USERS_PAGINATION,
    buildAdminUserForms,
    buildAdminUserPaginationItems,
    firstAdminUserErrorMessage,
    hasAdminUserRowChanged,
    isAdminUserRowSubmittable,
} from '../utils/adminUsers';

/**
 * Own admin user list, edit, delete, reset, search, and pagination state.
 *
 * @param {{ t: (key: string) => string }} options
 * @returns {Record<string, unknown>}
 */
export function useAdminUsersManagement({ t }) {
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
    const [pagination, setPagination] = useState(EMPTY_ADMIN_USERS_PAGINATION);

    const loadUsers = useCallback(
        async (pageNumber, searchTerm) => {
            setLoading(true);

            try {
                const response = await getAdminUsers({
                    page: pageNumber,
                    perPage: ADMIN_USERS_PER_PAGE,
                    search: searchTerm,
                });
                const nextUsers = response.data ?? [];
                const nextForms = buildAdminUserForms(nextUsers);
                const meta = response.meta ?? {};

                setUsers(nextUsers);
                setForms(nextForms);
                setInitialForms(nextForms);
                setFieldErrors({});
                setError('');
                setEditingUserId('');
                setPagination({
                    currentPage: meta.current_page ?? 1,
                    lastPage: meta.last_page ?? 1,
                    perPage: meta.per_page ?? ADMIN_USERS_PER_PAGE,
                    total: meta.total ?? nextUsers.length,
                    from: meta.from ?? 0,
                    to: meta.to ?? nextUsers.length,
                });
            } catch (requestError) {
                setUsers([]);
                setError(requestError?.response?.data?.message || t('adminUsers.loadError'));
                setPagination(EMPTY_ADMIN_USERS_PAGINATION);
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
        () => buildAdminUserPaginationItems(pagination.currentPage, pagination.lastPage),
        [pagination.currentPage, pagination.lastPage],
    );

    const saveUser = useCallback(
        async (userId) => {
            const form = forms[userId];
            const initialForm = initialForms[userId];

            if (!isAdminUserRowSubmittable(form) || !hasAdminUserRowChanged(form, initialForm)) {
                return;
            }

            setSavingUserId(userId);
            setFlash('');
            setFieldErrors((current) => ({
                ...current,
                [userId]: '',
            }));

            try {
                const response = await updateAdminUser(userId, {
                    name: form.name.trim(),
                    role: form.role,
                });
                const nextUser = response.data;

                setFlash(`${t('adminUsers.savedPrefix')} ${nextUser.email}.`);
                setEditingUserId('');
                await loadUsers(currentPage, activeSearch);
            } catch (requestError) {
                setFieldErrors((current) => ({
                    ...current,
                    [userId]: firstAdminUserErrorMessage(requestError, t('adminUsers.saveError')),
                }));
            } finally {
                setSavingUserId('');
            }
        },
        [activeSearch, currentPage, forms, initialForms, loadUsers, t],
    );

    const deleteUser = useCallback(async () => {
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
            await deleteAdminUser(userId);

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
                [userId]: firstAdminUserErrorMessage(requestError, t('adminUsers.deleteError')),
            }));
            setConfirmDeleteUser(null);
        } finally {
            setDeletingUserId('');
        }
    }, [activeSearch, confirmDeleteUser, currentPage, loadUsers, t, users.length]);

    const resetPassword = useCallback(
        async (userId) => {
            setResettingUserId(userId);
            setFlash('');
            setFieldErrors((current) => ({
                ...current,
                [userId]: '',
            }));

            try {
                const response = await resetAdminUserPassword(userId);
                setFlash(response.message || t('adminUsers.resetPasswordSuccess'));
            } catch (requestError) {
                setFieldErrors((current) => ({
                    ...current,
                    [userId]: firstAdminUserErrorMessage(
                        requestError,
                        t('adminUsers.resetPasswordError'),
                    ),
                }));
            } finally {
                setResettingUserId('');
            }
        },
        [t],
    );

    const confirmResetPassword = useCallback(async () => {
        if (!confirmResetUser) {
            return;
        }

        try {
            await resetPassword(String(confirmResetUser.id));
        } finally {
            setConfirmResetUser(null);
        }
    }, [confirmResetUser, resetPassword]);

    const handleSearchSubmit = useCallback(
        (event) => {
            event.preventDefault();
            setFlash('');
            setCurrentPage(1);
            setActiveSearch(searchInput.trim());
        },
        [searchInput],
    );

    const handleSearchReset = useCallback(() => {
        setSearchInput('');
        setFlash('');
        setCurrentPage(1);
        setActiveSearch('');
    }, []);

    const startEditing = useCallback((userId) => {
        setFlash('');
        setFieldErrors((current) => ({
            ...current,
            [userId]: '',
        }));
        setEditingUserId(userId);
    }, []);

    const cancelEditing = useCallback(
        (userId) => {
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
        },
        [initialForms],
    );

    const editingUser = useMemo(
        () => users.find((user) => String(user.id) === editingUserId) ?? null,
        [editingUserId, users],
    );
    const editingForm = useMemo(
        () => (editingUserId ? (forms[editingUserId] ?? null) : null),
        [editingUserId, forms],
    );

    return {
        users,
        forms,
        loading,
        error,
        flash,
        savingUserId,
        deletingUserId,
        fieldErrors,
        confirmDeleteUser,
        confirmResetUser,
        editingUserId,
        editingUser,
        editingForm,
        resettingUserId,
        searchInput,
        activeSearch,
        pagination,
        summary,
        paginationItems,
        setForms,
        setConfirmDeleteUser,
        setConfirmResetUser,
        setSearchInput,
        setCurrentPage,
        saveUser,
        deleteUser,
        confirmResetPassword,
        handleSearchSubmit,
        handleSearchReset,
        startEditing,
        cancelEditing,
    };
}
