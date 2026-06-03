import { memo, useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import AdminUserEditModalActions from '../../components/admin-users/AdminUserEditModalActions';
import AdminUserEditModalBody from '../../components/admin-users/AdminUserEditModalBody';

/**
 * Render the admin modal used to edit one user profile and reset that account password.
 */
function AdminUserEditModal({
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
    const dismissDisabled = saving || resetting;

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
        const handleKeyDown = (event) => {
            if (event.key === 'Escape' && !dismissDisabled) {
                onCancel();
            }
        };

        window.addEventListener('resize', updateModalViewportPosition);
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            body.style.overflow = previousBodyOverflow;
            documentElement.style.overflow = previousHtmlOverflow;
            window.removeEventListener('resize', updateModalViewportPosition);
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [dismissDisabled, onCancel, open]);

    const handleBackdropClick = useCallback(() => {
        if (!dismissDisabled) {
            onCancel();
        }
    }, [dismissDisabled, onCancel]);

    const handleModalClick = useCallback((event) => {
        event.stopPropagation();
    }, []);

    if (!open || !user || !form) {
        return null;
    }

    const content = (
        <div
            className="app-modal-backdrop"
            role="presentation"
            style={{ minHeight: `${modalViewportPosition.backdropHeight}px` }}
            onClick={handleBackdropClick}
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
                onClick={handleModalClick}
            >
                <AdminUserEditModalBody
                    error={error}
                    form={form}
                    t={t}
                    user={user}
                    onChange={onChange}
                />

                <AdminUserEditModalActions
                    resetting={resetting}
                    saving={saving}
                    t={t}
                    onCancel={onCancel}
                    cancelDisabled={dismissDisabled}
                    onResetPassword={onResetPassword}
                    onSave={onSave}
                />
            </div>
        </div>
    );

    if (typeof document === 'undefined') {
        return content;
    }

    return createPortal(content, document.body);
}

export default memo(AdminUserEditModal);
