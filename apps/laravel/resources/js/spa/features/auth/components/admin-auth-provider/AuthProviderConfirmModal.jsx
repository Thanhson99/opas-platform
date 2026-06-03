import { memo, useCallback, useMemo } from 'react';
import ConfirmModal from '../../../../components/ui/ConfirmModal';
import { buildAuthProviderConfirmContent } from './authProviderConfirmContent';

/**
 * Render save/enable/disable confirmation for the auth-provider admin screen.
 */
function AuthProviderConfirmModal({
    confirmState,
    provider,
    isLastActiveProvider,
    onCancel,
    onConfirmToggle,
    onConfirmSave,
    onLastProviderBlocked,
    t,
}) {
    const open = Boolean(confirmState);
    const content = useMemo(
        () => buildAuthProviderConfirmContent(confirmState, provider, isLastActiveProvider, t),
        [confirmState, isLastActiveProvider, provider, t],
    );

    const handleConfirm = useCallback(() => {
        if (!confirmState) {
            return;
        }

        if (confirmState.type === 'toggle') {
            if (isLastActiveProvider && !confirmState.nextEnabled) {
                onLastProviderBlocked();
                onCancel();

                return;
            }

            onConfirmToggle(confirmState.nextEnabled);
            onCancel();

            return;
        }

        onCancel();
        onConfirmSave();
    }, [
        confirmState,
        isLastActiveProvider,
        onCancel,
        onConfirmSave,
        onConfirmToggle,
        onLastProviderBlocked,
    ]);

    return (
        <ConfirmModal
            open={open}
            eyebrow={t('adminAuth.modal.eyebrow')}
            title={content.title}
            text={content.text}
            confirmLabel={content.confirmLabel}
            cancelLabel={t('common.cancel')}
            tone={content.tone}
            onCancel={onCancel}
            onConfirm={handleConfirm}
        />
    );
}

export default memo(AuthProviderConfirmModal);
