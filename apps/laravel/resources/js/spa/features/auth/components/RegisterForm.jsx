import ErrorState from '../../../components/ui/ErrorState';
import { RegisterEmailField, RegisterNameField } from './RegisterIdentityFields';
import { RegisterPasswordConfirmationField, RegisterPasswordField } from './RegisterPasswordFields';

/**
 * Render the email/password registration form.
 *
 * @param {{
 *   form: { name: string, email: string, password: string, password_confirmation: string },
 *   fieldErrors: Record<string, Array<string>>,
 *   error: string,
 *   weakPassword: boolean,
 *   missingPasswordRuleKeys: Array<string>,
 *   passwordMismatch: boolean,
 *   invalidEmail: boolean,
 *   canSubmit: boolean,
 *   submitting: boolean,
 *   t: (key: string) => string,
 *   onFormChange: import('react').Dispatch<import('react').SetStateAction<{ name: string, email: string, password: string, password_confirmation: string }>>,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function RegisterForm({
    form,
    fieldErrors,
    error,
    weakPassword,
    missingPasswordRuleKeys,
    passwordMismatch,
    invalidEmail,
    canSubmit,
    submitting,
    t,
    onFormChange,
    onSubmit,
}) {
    return (
        <form className="app-form" onSubmit={onSubmit}>
            <RegisterNameField
                fieldErrors={fieldErrors}
                form={form}
                t={t}
                onFormChange={onFormChange}
            />
            <RegisterEmailField
                fieldErrors={fieldErrors}
                form={form}
                invalidEmail={invalidEmail}
                t={t}
                onFormChange={onFormChange}
            />
            <RegisterPasswordField
                fieldErrors={fieldErrors}
                form={form}
                missingPasswordRuleKeys={missingPasswordRuleKeys}
                t={t}
                weakPassword={weakPassword}
                onFormChange={onFormChange}
            />
            <RegisterPasswordConfirmationField
                fieldErrors={fieldErrors}
                form={form}
                passwordMismatch={passwordMismatch}
                t={t}
                onFormChange={onFormChange}
            />
            {error ? <ErrorState text={error} /> : null}
            <button className="app-button app-button--primary" type="submit" disabled={!canSubmit}>
                {submitting ? t('auth.registerSubmitting') : t('auth.registerSubmit')}
            </button>
        </form>
    );
}
