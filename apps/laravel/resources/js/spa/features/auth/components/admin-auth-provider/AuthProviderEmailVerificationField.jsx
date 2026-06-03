import { memo, useCallback } from 'react';
import AuthProviderField from '../AuthProviderField';
import { buildProviderInputName } from './authProviderAdminFieldUtils';

/**
 * Render provider-specific email verification mode controls.
 */
function AuthProviderEmailVerificationField({ provider, value, onFieldBlur, onFieldChange, t }) {
    const inputId = `auth-provider-${provider.key}-email-verification-mode`;
    const handleBlur = useCallback(() => {
        onFieldBlur('email_verification_mode');
    }, [onFieldBlur]);
    const handleChange = useCallback(
        (event) => onFieldChange('email_verification_mode', event.target.value),
        [onFieldChange],
    );

    if (provider.key === 'email') {
        return <LockedEmailVerificationField provider={provider} t={t} />;
    }

    return (
        <AuthProviderField
            label={t('adminAuth.emailVerification.label')}
            inputId={inputId}
            description={t('adminAuth.emailVerification.help')}
            span="full"
        >
            <select
                id={inputId}
                className="app-input"
                name={buildProviderInputName(provider.key, 'email_verification_mode')}
                autoComplete="off"
                value={value}
                onBlur={handleBlur}
                onChange={handleChange}
            >
                <option value="">{t('adminAuth.emailVerification.inherit')}</option>
                <option value="required">{t('adminAuth.emailVerification.required')}</option>
                <option value="optional">{t('adminAuth.emailVerification.optional')}</option>
                <option value="disabled">{t('adminAuth.emailVerification.disabled')}</option>
            </select>
        </AuthProviderField>
    );
}

export default memo(AuthProviderEmailVerificationField);

const LockedEmailVerificationField = memo(function LockedEmailVerificationField({ provider, t }) {
    const inputId = `auth-provider-${provider.key}-email-verification-locked`;

    return (
        <AuthProviderField
            label={t('adminAuth.emailVerification.label')}
            inputId={inputId}
            description={t('adminAuth.emailVerification.emailLockedHelp')}
            span="full"
        >
            <input
                id={inputId}
                className="app-input"
                value={t('adminAuth.emailVerification.required')}
                disabled
                readOnly
            />
        </AuthProviderField>
    );
});
