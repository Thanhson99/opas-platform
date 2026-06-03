/**
 * Build the localized content for the provider save/enable/disable confirmation modal.
 */
export function buildAuthProviderConfirmContent(confirmState, provider, isLastActiveProvider, t) {
    return {
        title: getConfirmTitle(confirmState, t),
        text: getConfirmText(confirmState, provider, isLastActiveProvider, t),
        confirmLabel: getConfirmLabel(confirmState, t),
        tone: getConfirmTone(confirmState),
    };
}

function getConfirmTitle(confirmState, t) {
    if (confirmState?.type === 'save') {
        return t('adminAuth.modal.saveTitle');
    }

    return confirmState?.nextEnabled
        ? t('adminAuth.modal.enableTitle')
        : t('adminAuth.modal.disableTitle');
}

function getConfirmText(confirmState, provider, isLastActiveProvider, t) {
    if (confirmState?.type === 'save') {
        return t('adminAuth.modal.saveText');
    }

    if (confirmState?.nextEnabled) {
        return `${t('adminAuth.confirm.prefix')} "${provider.display_name}" ${t(
            'adminAuth.confirm.enableSuffix',
        )}`;
    }

    if (isLastActiveProvider) {
        return t('adminAuth.lastProviderText');
    }

    return `${t('adminAuth.confirm.prefix')} "${provider.display_name}" ${t(
        'adminAuth.confirm.disableSuffix',
    )}`;
}

function getConfirmLabel(confirmState, t) {
    if (confirmState?.type === 'save') {
        return t('adminAuth.modal.confirmSave');
    }

    return confirmState?.nextEnabled
        ? t('adminAuth.modal.confirmEnable')
        : t('adminAuth.modal.confirmDisable');
}

function getConfirmTone(confirmState) {
    if (confirmState?.type === 'save' || confirmState?.nextEnabled) {
        return 'primary';
    }

    return 'danger';
}
