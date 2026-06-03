import { memo, useCallback } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render the masked view for a stored provider secret.
 */
function MaskedSecretValue({ field, onEdit, t }) {
    const handleEdit = useCallback(() => {
        onEdit(field, true);
    }, [field, onEdit]);

    return (
        <div className="app-secret-field">
            <div className="app-secret-field__masked">
                <span className="app-secret-field__masked-value">••••••••••••</span>
                <span className="app-secret-field__masked-label">
                    {t('adminAuth.secretStored')}
                </span>
            </div>
            <div className="app-secret-field__actions">
                <button
                    type="button"
                    className="app-secret-field__button"
                    onClick={handleEdit}
                    title={t('adminAuth.editSecret')}
                >
                    <AppIcon name="edit" />
                    {t('adminAuth.editSecret')}
                </button>
            </div>
            <p className="app-field__hint">{t('adminAuth.secretMaskedHint')}</p>
        </div>
    );
}

export default memo(MaskedSecretValue);
