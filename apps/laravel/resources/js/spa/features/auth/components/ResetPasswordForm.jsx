import ErrorState from '../../../components/ui/ErrorState';
import SensitiveInput from './SensitiveInput';

/**
 * Render the password reset completion form.
 *
 * @param {{
 *   form: { email: string, password: string, password_confirmation: string },
 *   submitting: boolean,
 *   error: string,
 *   invalidEmail: boolean,
 *   weakPassword: boolean,
 *   missingPasswordRuleKeys: Array<string>,
 *   passwordMismatch: boolean,
 *   canSubmit: boolean,
 *   t: (key: string) => string,
 *   onFormChange: import('react').Dispatch<import('react').SetStateAction<{ email: string, password: string, password_confirmation: string }>>,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function ResetPasswordForm({
    form,
    submitting,
    error,
    invalidEmail,
    weakPassword,
    missingPasswordRuleKeys,
    passwordMismatch,
    canSubmit,
    t,
    onFormChange,
    onSubmit,
}) {
    return (
        <>
            {error ? <ErrorState text={error} /> : null}
            <form className="app-form" onSubmit={onSubmit}>
                <ResetEmailField
                    form={form}
                    invalidEmail={invalidEmail}
                    t={t}
                    onFormChange={onFormChange}
                />
                <ResetPasswordField
                    form={form}
                    missingPasswordRuleKeys={missingPasswordRuleKeys}
                    t={t}
                    weakPassword={weakPassword}
                    onFormChange={onFormChange}
                />
                <ResetPasswordConfirmationField
                    form={form}
                    passwordMismatch={passwordMismatch}
                    t={t}
                    onFormChange={onFormChange}
                />
                <button
                    className="app-button app-button--primary"
                    type="submit"
                    disabled={!canSubmit}
                >
                    {submitting ? t('auth.resetPasswordSubmitting') : t('auth.resetPasswordSubmit')}
                </button>
            </form>
        </>
    );
}

function ResetEmailField({ form, invalidEmail, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="reset-password-email">
                {t('auth.email')}
            </label>
            <input
                id="reset-password-email"
                className={`app-input ${invalidEmail ? 'app-input--invalid' : ''}`}
                type="email"
                autoComplete="email"
                value={form.email}
                aria-invalid={invalidEmail}
                aria-describedby="reset-password-email-help"
                onChange={(event) =>
                    onFormChange((value) => ({
                        ...value,
                        email: event.target.value,
                    }))
                }
                required
            />
            {invalidEmail ? <p className="app-field__error">{t('auth.invalidEmail')}</p> : null}
            <p id="reset-password-email-help" className="app-field__help">
                {t('auth.resetPasswordEmailHelp')}
            </p>
        </div>
    );
}

function ResetPasswordField({ form, missingPasswordRuleKeys, t, weakPassword, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="reset-password-new">
                {t('auth.password')}
            </label>
            <SensitiveInput
                id="reset-password-new"
                value={form.password}
                invalid={weakPassword}
                required
                autoComplete="new-password"
                revealLabel={t('auth.showValue')}
                concealLabel={t('auth.hideValue')}
                onChange={(event) =>
                    onFormChange((value) => ({
                        ...value,
                        password: event.target.value,
                    }))
                }
            />
            {weakPassword ? (
                <>
                    <p className="app-field__error">{t('auth.passwordRuleIntro')}</p>
                    {missingPasswordRuleKeys.map((ruleKey) => (
                        <p key={ruleKey} className="app-field__hint">
                            {t('auth.passwordRuleFail')} {t(`auth.${ruleKey}`)}
                        </p>
                    ))}
                </>
            ) : null}
        </div>
    );
}

function ResetPasswordConfirmationField({ form, passwordMismatch, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="reset-password-confirmation">
                {t('auth.confirmPassword')}
            </label>
            <SensitiveInput
                id="reset-password-confirmation"
                value={form.password_confirmation}
                invalid={passwordMismatch}
                required
                autoComplete="new-password"
                revealLabel={t('auth.showValue')}
                concealLabel={t('auth.hideValue')}
                onChange={(event) =>
                    onFormChange((value) => ({
                        ...value,
                        password_confirmation: event.target.value,
                    }))
                }
            />
            {passwordMismatch ? (
                <p className="app-field__error">{t('auth.passwordConfirmMismatch')}</p>
            ) : null}
        </div>
    );
}
