import { memo, useCallback } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';
import SensitiveInput from '../SensitiveInput';
import { buildProviderInputName } from './authProviderAdminFieldUtils';

/**
 * Render the editable sensitive input for one provider secret.
 */
function EditableSecretValue({
    provider,
    field,
    inputId,
    value,
    placeholder,
    errorMessage,
    hasStoredSecret,
    onFieldBlur,
    onConfigChange,
    onSecretEditChange,
    t,
}) {
    const handleBlur = useCallback(() => {
        onFieldBlur(`secret_config.${field}`);
    }, [field, onFieldBlur]);

    const handleChange = useCallback(
        (event) => onConfigChange('secret_config', field, event.target.value),
        [field, onConfigChange],
    );

    const handleCancelEdit = useCallback(() => {
        onSecretEditChange(field, false);
        onConfigChange('secret_config', field, '');
    }, [field, onConfigChange, onSecretEditChange]);

    return (
        <div className="app-secret-field">
            <SensitiveInput
                id={inputId}
                value={value}
                invalid={Boolean(errorMessage)}
                name={buildProviderInputName(provider.key, field, 'secret')}
                autoComplete="new-password"
                placeholder={placeholder}
                revealLabel={t('auth.showValue')}
                concealLabel={t('auth.hideValue')}
                onBlur={handleBlur}
                onChange={handleChange}
            />
            {hasStoredSecret ? (
                <div className="app-secret-field__actions">
                    <button
                        type="button"
                        className="app-secret-field__button app-secret-field__button--muted"
                        onClick={handleCancelEdit}
                        title={t('adminAuth.cancelSecretEdit')}
                    >
                        <AppIcon name="refresh" />
                        {t('adminAuth.cancelSecretEdit')}
                    </button>
                </div>
            ) : null}
        </div>
    );
}

export default memo(EditableSecretValue);
