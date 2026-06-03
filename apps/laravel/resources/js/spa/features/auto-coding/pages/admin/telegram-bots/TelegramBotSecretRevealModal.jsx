import { memo, useCallback, useEffect } from 'react';
import { createPortal } from 'react-dom';
import AppIcon from '../../../../../components/icons/AppIcon';
import SensitiveInput from '../../../../auth/components/SensitiveInput';

/**
 * Render the password-confirmed secret reveal modal.
 *
 * @param {{
 *   open: boolean,
 *   t: (key: string) => string,
 *   loading: boolean,
 *   password: string,
 *   error: string,
 *   onPasswordChange: (value: string) => void,
 *   onClose: () => void,
 *   onConfirm: () => void,
 * }} props
 * @returns {import('react').ReactPortal | null}
 */
function TelegramBotSecretRevealModal({
    open,
    t,
    loading,
    password,
    error,
    onPasswordChange,
    onClose,
    onConfirm,
}) {
    useEffect(() => {
        if (!open || typeof document === 'undefined') {
            return undefined;
        }

        const { body, documentElement } = document;
        const previousBodyOverflow = body.style.overflow;
        const previousHtmlOverflow = documentElement.style.overflow;
        const handleKeyDown = (event) => {
            if (event.key === 'Escape' && !loading) {
                onClose();
            }
        };

        body.style.overflow = 'hidden';
        documentElement.style.overflow = 'hidden';
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            body.style.overflow = previousBodyOverflow;
            documentElement.style.overflow = previousHtmlOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [loading, onClose, open]);

    const handleModalClick = useCallback((event) => {
        event.stopPropagation();
    }, []);
    const handlePasswordChange = useCallback(
        (event) => onPasswordChange(event.target.value),
        [onPasswordChange],
    );
    const handleBackdropClick = useCallback(() => {
        if (!loading) {
            onClose();
        }
    }, [loading, onClose]);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            className="admin-telegram-bots__drawer-backdrop admin-telegram-bots__secret-backdrop"
            role="presentation"
            onClick={handleBackdropClick}
        >
            <div
                className="admin-telegram-bots__secret-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="telegram-bot-secret-modal-title"
                aria-busy={loading}
                onClick={handleModalClick}
            >
                <div className="admin-telegram-bots__secret-modal-body">
                    <p className="admin-telegram-bots__section-eyebrow">
                        {t('adminTelegramBots.secretEyebrow')}
                    </p>
                    <h3 id="telegram-bot-secret-modal-title">
                        {t('adminTelegramBots.secretModalTitle')}
                    </h3>
                    <p>{t('adminTelegramBots.secretModalText')}</p>
                    <label className="admin-telegram-bots__field">
                        <span>{t('adminTelegramBots.fields.currentPassword')}</span>
                        <SensitiveInput
                            value={password}
                            onChange={handlePasswordChange}
                            placeholder={t('adminTelegramBots.placeholders.currentPassword')}
                            revealLabel={t('auth.showValue')}
                            concealLabel={t('auth.hideValue')}
                        />
                    </label>
                    {error ? <p className="admin-telegram-bots__secret-error">{error}</p> : null}
                </div>
                <div className="admin-telegram-bots__secret-modal-actions">
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        disabled={loading}
                        onClick={onClose}
                        title={t('common.cancel')}
                    >
                        <AppIcon name="x" />
                        {t('common.cancel')}
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--primary"
                        onClick={onConfirm}
                        disabled={loading || password.trim() === ''}
                        title={t('adminTelegramBots.confirmReveal')}
                    >
                        <AppIcon name="eye" />
                        {loading
                            ? t('adminTelegramBots.revealingSecret')
                            : t('adminTelegramBots.confirmReveal')}
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}

export default memo(TelegramBotSecretRevealModal);
