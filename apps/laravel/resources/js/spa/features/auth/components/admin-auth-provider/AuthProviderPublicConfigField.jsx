import { memo, useCallback } from 'react';
import AuthProviderField from '../AuthProviderField';
import { buildFieldMeta } from '../../pages/admin/authProviderAdminForm.helpers';
import { buildProviderInputName, getFieldError } from './authProviderAdminFieldUtils';

/**
 * Render one editable public provider config field.
 */
function AuthProviderPublicConfigField({
    provider,
    form,
    field,
    fieldIssues,
    serverErrors,
    touchedFields,
    onFieldBlur,
    onConfigChange,
    t,
}) {
    const meta = buildFieldMeta(t, field, {
        callbackUrl: provider.metadata?.callback_url ?? null,
        providerDisplayName: provider.display_name,
    });
    const errorMessage = getFieldError(
        fieldIssues,
        serverErrors,
        touchedFields,
        `public_config.${field}`,
    );
    const inputId = `auth-provider-${provider.key}-public-${field}`;
    const handleBlur = useCallback(() => {
        onFieldBlur(`public_config.${field}`);
    }, [field, onFieldBlur]);
    const handleChange = useCallback(
        (event) => onConfigChange('public_config', field, event.target.value),
        [field, onConfigChange],
    );

    return (
        <AuthProviderField
            label={meta.label}
            inputId={inputId}
            description={meta.description}
            error={errorMessage}
            required={(provider.required_public_keys ?? []).includes(field)}
            span={meta.span}
        >
            <input
                id={inputId}
                className={`app-input ${errorMessage ? 'app-input--invalid' : ''}`}
                name={buildProviderInputName(provider.key, field, 'public')}
                autoComplete="off"
                data-lpignore="true"
                data-1p-ignore="true"
                value={form.public_config[field]}
                placeholder={meta.placeholder}
                aria-invalid={Boolean(errorMessage)}
                onBlur={handleBlur}
                onChange={handleChange}
            />
        </AuthProviderField>
    );
}

export default memo(AuthProviderPublicConfigField);
