import { memo } from 'react';
import AuthProviderField from '../AuthProviderField';
import { buildBaseMeta } from '../../pages/admin/authProviderAdminForm.helpers';
import AuthProviderIconField from './AuthProviderIconField';
import AuthProviderStandardBaseInput from './AuthProviderStandardBaseInput';
import { getFieldError } from './authProviderAdminFieldUtils';

/**
 * Render one editable base provider field.
 */
function AuthProviderBaseField({
    provider,
    form,
    field,
    fieldIssues,
    serverErrors,
    touchedFields,
    onFieldBlur,
    onFieldChange,
    t,
}) {
    const meta = buildBaseMeta(t, field);
    const errorMessage = getFieldError(fieldIssues, serverErrors, touchedFields, field);
    const isIconField = field === 'icon';
    const inputId = `auth-provider-${provider.key}-${field}`;

    return (
        <AuthProviderField
            label={meta.label}
            inputId={inputId}
            description={meta.description}
            error={errorMessage}
            required={field === 'display_name' || field === 'sort_order'}
            span={meta.span}
            className={getBaseFieldClassName(field)}
        >
            {isIconField ? (
                <AuthProviderIconField
                    providerKey={provider.key}
                    field={field}
                    inputId={inputId}
                    value={form[field]}
                    errorMessage={errorMessage}
                    onFieldBlur={onFieldBlur}
                    onFieldChange={onFieldChange}
                    t={t}
                />
            ) : (
                <AuthProviderStandardBaseInput
                    providerKey={provider.key}
                    field={field}
                    inputId={inputId}
                    value={form[field]}
                    placeholder={meta.placeholder}
                    errorMessage={errorMessage}
                    onFieldBlur={onFieldBlur}
                    onFieldChange={onFieldChange}
                />
            )}
        </AuthProviderField>
    );
}

function getBaseFieldClassName(field) {
    if (field === 'icon') {
        return 'app-field--icon';
    }

    if (field === 'sort_order') {
        return 'app-field--order';
    }

    return '';
}

export default memo(AuthProviderBaseField);
