import { memo } from 'react';
import AuthProviderField from '../AuthProviderField';
import { buildFieldMeta } from '../../pages/admin/authProviderAdminForm.helpers';
import { getFieldError } from './authProviderAdminFieldUtils';
import EditableSecretValue from './EditableSecretValue';
import MaskedSecretValue from './MaskedSecretValue';

/**
 * Render one masked or editable secret config field.
 */
function AuthProviderSecretField({
    provider,
    form,
    field,
    fieldIssues,
    serverErrors,
    touchedFields,
    secretEditState,
    onFieldBlur,
    onConfigChange,
    onSecretEditChange,
    t,
}) {
    const meta = buildFieldMeta(t, field);
    const errorMessage = getFieldError(
        fieldIssues,
        serverErrors,
        touchedFields,
        `secret_config.${field}`,
    );
    const secretStateKey = `${provider.key}.${field}`;
    const hasStoredSecret = Boolean(provider.secret_status?.[field]);
    const value = form.secret_config[field];
    const hasTypedSecret = String(value ?? '').trim() !== '';
    const isEditingSecret = Boolean(secretEditState[secretStateKey]) || hasTypedSecret;
    const inputId = `auth-provider-${provider.key}-secret-${field}`;

    return (
        <AuthProviderField
            label={meta.label}
            inputId={isEditingSecret ? inputId : undefined}
            description={meta.description}
            error={errorMessage}
            required={(provider.required_secret_keys ?? []).includes(field)}
            span={meta.span}
            badge={
                provider.secret_status?.[field] ? (
                    <span className="app-field__badge">{t('adminAuth.secretStored')}</span>
                ) : null
            }
        >
            {hasStoredSecret && !isEditingSecret ? (
                <MaskedSecretValue field={field} onEdit={onSecretEditChange} t={t} />
            ) : (
                <EditableSecretValue
                    provider={provider}
                    field={field}
                    inputId={inputId}
                    value={value}
                    placeholder={meta.placeholder}
                    errorMessage={errorMessage}
                    hasStoredSecret={hasStoredSecret}
                    onFieldBlur={onFieldBlur}
                    onConfigChange={onConfigChange}
                    onSecretEditChange={onSecretEditChange}
                    t={t}
                />
            )}
        </AuthProviderField>
    );
}

export default memo(AuthProviderSecretField);
