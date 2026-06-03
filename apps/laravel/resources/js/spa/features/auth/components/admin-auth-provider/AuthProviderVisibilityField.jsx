import { memo, useCallback } from 'react';
import AuthProviderField from '../AuthProviderField';
import { buildProviderInputName } from './authProviderAdminFieldUtils';

/**
 * Render the admin visibility selector for one auth provider.
 */
function AuthProviderVisibilityField({ provider, value, onFieldBlur, onFieldChange, t }) {
    const inputId = `auth-provider-${provider.key}-visibility`;
    const handleBlur = useCallback(() => {
        onFieldBlur('visibility');
    }, [onFieldBlur]);
    const handleChange = useCallback(
        (event) => onFieldChange('visibility', event.target.value),
        [onFieldChange],
    );

    return (
        <AuthProviderField
            label={t('adminAuth.visibility.label')}
            inputId={inputId}
            description={t('adminAuth.visibility.help')}
            span="half"
        >
            <select
                id={inputId}
                className="app-input"
                name={buildProviderInputName(provider.key, 'visibility')}
                autoComplete="off"
                value={value}
                onBlur={handleBlur}
                onChange={handleChange}
            >
                <option value="public">{t('adminAuth.visibility.public')}</option>
                <option value="hidden">{t('adminAuth.visibility.hidden')}</option>
                <option value="admin_only">{t('adminAuth.visibility.adminOnly')}</option>
            </select>
        </AuthProviderField>
    );
}

export default memo(AuthProviderVisibilityField);
