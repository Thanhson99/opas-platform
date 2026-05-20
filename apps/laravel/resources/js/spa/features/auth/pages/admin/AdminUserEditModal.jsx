import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render the admin modal used to edit one user profile and reset that account password.
 */
export default function AdminUserEditModal({
    open,
    t,
    form,
    user,
    error,
    saving,
    resetting,
    onChange,
    onCancel,
    onSave,
    onResetPassword,
}) {
    const [modalViewportPosition, setModalViewportPosition] = useState({
        top: 0,
        left: 0,
        backdropHeight: 0,
    });

    useEffect(() => {
        if (!open || typeof document === 'undefined') {
            return undefined;
        }

        const { body, documentElement } = document;
        const previousBodyOverflow = body.style.overflow;
        const previousHtmlOverflow = documentElement.style.overflow;

        const updateModalViewportPosition = () => {
            const scrollTop = window.scrollY || documentElement.scrollTop || body.scrollTop || 0;
            const scrollLeft = window.scrollX || documentElement.scrollLeft || body.scrollLeft || 0;
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;
            const documentHeight = Math.max(
                body.scrollHeight,
                body.offsetHeight,
                documentElement.clientHeight,
                documentElement.scrollHeight,
                documentElement.offsetHeight,
            );

            setModalViewportPosition({
                top: scrollTop + viewportHeight / 2,
                left: scrollLeft + viewportWidth / 2,
                backdropHeight: Math.max(documentHeight, scrollTop + viewportHeight),
            });
        };

        updateModalViewportPosition();
        body.style.overflow = 'hidden';
        documentElement.style.overflow = 'hidden';
        window.addEventListener('resize', updateModalViewportPosition);

        return () => {
            body.style.overflow = previousBodyOverflow;
            documentElement.style.overflow = previousHtmlOverflow;
            window.removeEventListener('resize', updateModalViewportPosition);
        };
    }, [open]);

    if (!open || !user || !form) {
        return null;
    }

    const content = (
        <div
            className="app-modal-backdrop"
            role="presentation"
            style={{ minHeight: `${modalViewportPosition.backdropHeight}px` }}
            onClick={onCancel}
        >
            <div
                className="app-modal app-modal--wide"
                role="dialog"
                aria-modal="true"
                aria-labelledby="admin-user-edit-modal-title"
                style={{
                    top: `${modalViewportPosition.top}px`,
                    left: `${modalViewportPosition.left}px`,
                }}
                onClick={(event) => event.stopPropagation()}
            >
                <div className="app-modal__body app-user-edit-modal">
                    <div className="app-user-edit-modal__hero">
                        <p className="app-modal__eyebrow">{t('adminUsers.editModal.eyebrow')}</p>
                        <h3 className="app-modal__title" id="admin-user-edit-modal-title">
                            {t('adminUsers.editModal.title')}
                        </h3>
                        <p className="app-modal__text">{t('adminUsers.editModal.text')}</p>
                    </div>

                    <div className="app-user-edit-modal__panel">
                        <div className="app-form-grid app-user-edit-modal__grid">
                            <label className="app-field">
                                <span className="app-field__label">
                                    {t('adminUsers.columns.name')}
                                </span>
                                <input
                                    type="text"
                                    className={`app-input ${error ? 'app-input--invalid' : ''}`}
                                    value={form.name}
                                    onChange={(event) => onChange('name', event.target.value)}
                                />
                            </label>

                            <label className="app-field">
                                <span className="app-field__label">
                                    {t('adminUsers.columns.email')}
                                </span>
                                <input
                                    type="email"
                                    className="app-input"
                                    value={form.email}
                                    disabled
                                    readOnly
                                />
                            </label>

                            <label className="app-field">
                                <span className="app-field__label">
                                    {t('adminUsers.columns.role')}
                                </span>
                                <select
                                    className={`app-input ${error ? 'app-input--invalid' : ''}`}
                                    value={form.role}
                                    disabled={Boolean(user.is_current_user)}
                                    onChange={(event) => onChange('role', event.target.value)}
                                >
                                    {(user.available_roles ?? []).map((role) => (
                                        <option key={role.value} value={role.value}>
                                            {role.label}
                                        </option>
                                    ))}
                                </select>
                                {user.is_current_user ? (
                                    <span className="app-field__help">
                                        {t('adminUsers.selfRoleLocked')}
                                    </span>
                                ) : null}
                            </label>
                        </div>
                    </div>

                    <div className="app-user-edit-modal__meta-grid">
                        <div className="app-user-edit-modal__meta-card">
                            <span className="app-user-edit-modal__meta-label">
                                {t('adminUsers.columns.status')}
                            </span>
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
                        </div>

                        <div className="app-user-edit-modal__meta-card">
                            <span className="app-user-edit-modal__meta-label">
                                {t('adminUsers.columns.providers')}
                            </span>
                            <div className="app-user-edit-modal__providers">
                                {(user.linked_providers ?? []).length > 0 ? (
                                    (user.linked_providers ?? []).map((provider) => (
                                        <span
                                            key={provider.key}
                                            className="app-user-edit-modal__provider-chip"
                                        >
                                            <AppIcon name={provider.icon ?? provider.key} />
                                            <span>{provider.display_name}</span>
                                        </span>
                                    ))
                                ) : (
                                    <span className="app-user-edit-modal__provider-empty">
                                        <AppIcon name="link" />
                                        <span>{t('adminUsers.noLinkedProviders')}</span>
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className="app-user-edit-modal__meta-card">
                            <span className="app-user-edit-modal__meta-label">
                                {t('adminUsers.columns.verifiedAt')}
                            </span>
                            <span className="app-user-edit-modal__value">
                                {user.email_verified_at
                                    ? new Date(user.email_verified_at).toLocaleString()
                                    : t('adminUsers.notAvailable')}
                            </span>
                        </div>
                    </div>

                    {error ? <p className="app-field__error">{error}</p> : null}
                </div>

                <div className="app-modal__actions app-user-edit-modal__actions">
                    <button
                        type="button"
                        className="app-button app-button--danger"
                        disabled={saving || resetting}
                        onClick={onResetPassword}
                    >
                        {resetting
                            ? t('adminUsers.resettingPassword')
                            : t('adminUsers.resetPassword')}
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={onCancel}
                    >
                        {t('common.cancel')}
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--primary"
                        disabled={saving}
                        onClick={onSave}
                    >
                        {saving ? t('adminUsers.saving') : t('adminUsers.save')}
                    </button>
                </div>
            </div>
        </div>
    );

    if (typeof document === 'undefined') {
        return content;
    }

    return createPortal(content, document.body);
}
