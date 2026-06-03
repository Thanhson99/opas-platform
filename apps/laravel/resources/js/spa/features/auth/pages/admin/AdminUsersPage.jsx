import { lazy, Suspense, useCallback } from 'react';
import { useAuth } from '../../context/AuthContext';
import AdminUsersHero from '../../components/admin-users/AdminUsersHero';
import AdminUsersListSection from '../../components/admin-users/AdminUsersListSection';
import ErrorState from '../../../../components/ui/ErrorState';
import LoadingState from '../../../../components/ui/LoadingState';
import { useAdminUsersManagement } from '../../hooks/useAdminUsersManagement';
import { useLanguage } from '../../../i18n/context/LanguageContext';

const AdminUserEditModal = lazy(() => import('./AdminUserEditModal'));
const ConfirmModal = lazy(() => import('../../../../components/ui/ConfirmModal'));

/**
 * Render the admin user-management screen with edit, delete, reset, and search actions.
 */
export default function AdminUsersPage() {
    const { t } = useLanguage();
    const { user: currentUser } = useAuth();
    const {
        users,
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
    } = useAdminUsersManagement({ t });

    const handleEditFormChange = useCallback(
        (field, value) => {
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
        },
        [editingForm, editingUserId, setForms],
    );

    const handleEditCancel = useCallback(() => {
        if (editingUserId) {
            cancelEditing(editingUserId);
        }
    }, [cancelEditing, editingUserId]);

    const handleEditSave = useCallback(() => {
        if (editingUserId) {
            void saveUser(editingUserId);
        }
    }, [editingUserId, saveUser]);

    const handleResetPasswordRequest = useCallback(() => {
        if (editingUserId) {
            setConfirmResetUser(editingUser);
        }
    }, [editingUser, editingUserId, setConfirmResetUser]);

    const handleDeleteCancel = useCallback(() => {
        setConfirmDeleteUser(null);
    }, [setConfirmDeleteUser]);

    const handleResetCancel = useCallback(() => {
        setConfirmResetUser(null);
    }, [setConfirmResetUser]);

    const handleDeleteConfirm = useCallback(() => {
        void deleteUser();
    }, [deleteUser]);

    const handleResetConfirm = useCallback(() => {
        void confirmResetPassword();
    }, [confirmResetPassword]);

    if (loading && users.length === 0) {
        return <LoadingState text={t('adminUsers.loading')} />;
    }

    if (error && users.length === 0) {
        return <ErrorState text={error} />;
    }

    return (
        <div className="app-shell">
            <AdminUsersHero summary={summary} t={t} />

            {flash ? (
                <div className="app-provider-note app-provider-note--success">{flash}</div>
            ) : null}
            {error ? (
                <div className="app-provider-note app-provider-note--warning">{error}</div>
            ) : null}

            <AdminUsersListSection
                activeSearch={activeSearch}
                currentUser={currentUser}
                deletingUserId={deletingUserId}
                pagination={pagination}
                paginationItems={paginationItems}
                searchInput={searchInput}
                t={t}
                users={users}
                onClearSearch={handleSearchReset}
                onDeleteUser={setConfirmDeleteUser}
                onEditUser={startEditing}
                onPageChange={setCurrentPage}
                onSearchChange={setSearchInput}
                onSearchSubmit={handleSearchSubmit}
            />

            {editingUser && editingForm ? (
                <Suspense fallback={null}>
                    <AdminUserEditModal
                        open
                        t={t}
                        user={editingUser}
                        form={editingForm}
                        error={editingUserId ? fieldErrors[editingUserId] : ''}
                        saving={Boolean(editingUserId) && savingUserId === editingUserId}
                        resetting={Boolean(editingUserId) && resettingUserId === editingUserId}
                        onChange={handleEditFormChange}
                        onCancel={handleEditCancel}
                        onSave={handleEditSave}
                        onResetPassword={handleResetPasswordRequest}
                    />
                </Suspense>
            ) : null}

            {confirmDeleteUser ? (
                <Suspense fallback={null}>
                    <ConfirmModal
                        open
                        eyebrow={t('adminUsers.deleteModal.eyebrow')}
                        title={t('adminUsers.deleteModal.title')}
                        text={`${t('adminUsers.deleteModal.text')} ${confirmDeleteUser.email}`}
                        confirmLabel={t('adminUsers.deleteModal.confirm')}
                        cancelLabel={t('common.cancel')}
                        tone="danger"
                        confirmDisabled={deletingUserId === String(confirmDeleteUser.id)}
                        cancelDisabled={deletingUserId === String(confirmDeleteUser.id)}
                        onCancel={handleDeleteCancel}
                        onConfirm={handleDeleteConfirm}
                    />
                </Suspense>
            ) : null}

            {confirmResetUser ? (
                <Suspense fallback={null}>
                    <ConfirmModal
                        open
                        eyebrow={t('adminUsers.resetModal.eyebrow')}
                        title={t('adminUsers.resetModal.title')}
                        text={`${t('adminUsers.resetModal.text')} ${confirmResetUser.email}`}
                        confirmLabel={t('adminUsers.resetModal.confirm')}
                        cancelLabel={t('common.cancel')}
                        tone="danger"
                        confirmDisabled={resettingUserId === String(confirmResetUser.id)}
                        cancelDisabled={resettingUserId === String(confirmResetUser.id)}
                        onCancel={handleResetCancel}
                        onConfirm={handleResetConfirm}
                    />
                </Suspense>
            ) : null}
        </div>
    );
}
