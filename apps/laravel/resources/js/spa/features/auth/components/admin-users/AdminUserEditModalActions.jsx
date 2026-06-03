import { memo } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render admin user edit modal action buttons.
 *
 * @param {{
 *   saving: boolean,
 *   resetting: boolean,
 *   cancelDisabled?: boolean,
 *   t: (key: string) => string,
 *   onCancel: () => void,
 *   onResetPassword: () => void,
 *   onSave: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AdminUserEditModalActions({
    saving,
    resetting,
    cancelDisabled = false,
    t,
    onCancel,
    onResetPassword,
    onSave,
}) {
    return (
        <div className="app-modal__actions app-user-edit-modal__actions">
            <button
                type="button"
                className="app-button app-button--danger"
                disabled={saving || resetting}
                onClick={onResetPassword}
                title={t('adminUsers.resetPassword')}
            >
                <AppIcon name="lock" />
                {resetting ? t('adminUsers.resettingPassword') : t('adminUsers.resetPassword')}
            </button>
            <button
                type="button"
                className="app-button app-button--ghost"
                disabled={cancelDisabled}
                onClick={onCancel}
                title={t('common.cancel')}
            >
                <AppIcon name="x" />
                {t('common.cancel')}
            </button>
            <button
                type="button"
                className="app-button app-button--primary"
                disabled={saving}
                onClick={onSave}
                title={t('adminUsers.save')}
            >
                <AppIcon name="check" />
                {saving ? t('adminUsers.saving') : t('adminUsers.save')}
            </button>
        </div>
    );
}

export default memo(AdminUserEditModalActions);
