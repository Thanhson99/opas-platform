import { memo, useCallback, useMemo } from 'react';
import AppIcon, { hasAppIcon } from '../../../../components/icons/AppIcon';
import { getAvailableIconNames } from '../../pages/admin/authProviderAdminForm.helpers';
import { buildProviderInputName } from './authProviderAdminFieldUtils';

/**
 * Render the provider icon selector with an inline preview.
 */
function AuthProviderIconField({
    providerKey,
    field,
    inputId,
    value,
    errorMessage,
    onFieldBlur,
    onFieldChange,
    t,
}) {
    const iconName = value.trim();
    const availableIconNames = useMemo(() => getAvailableIconNames(), []);
    const showIconPreview = iconName !== '' && hasAppIcon(iconName);
    const handleBlur = useCallback(() => {
        onFieldBlur(field);
    }, [field, onFieldBlur]);
    const handleChange = useCallback(
        (event) => onFieldChange(field, event.target.value),
        [field, onFieldChange],
    );

    return (
        <>
            <div className="app-input-preview-row app-input-preview-row--compact">
                <select
                    id={inputId}
                    className={`app-input ${errorMessage ? 'app-input--invalid' : ''}`}
                    name={buildProviderInputName(providerKey, field)}
                    autoComplete="off"
                    value={value}
                    aria-invalid={Boolean(errorMessage)}
                    onBlur={handleBlur}
                    onChange={handleChange}
                >
                    <option value="">{t('adminAuth.iconPreview.selectPlaceholder')}</option>
                    {availableIconNames.map((iconOption) => (
                        <option key={iconOption} value={iconOption}>
                            {iconOption}
                        </option>
                    ))}
                </select>
                <div className={`app-icon-preview ${showIconPreview ? 'is-active' : 'is-empty'}`}>
                    {showIconPreview ? (
                        <AppIcon name={iconName} />
                    ) : (
                        <span>{t('adminAuth.iconPreview.empty')}</span>
                    )}
                </div>
            </div>
            <p className="app-field__hint">
                {t('adminAuth.iconPreview.availablePrefix')} {availableIconNames.join(', ')}
            </p>
        </>
    );
}

export default memo(AuthProviderIconField);
