import SensitiveInput from './SensitiveInput';

/**
 * Render the registration password field.
 *
 * @param {{
 *   fieldErrors: Record<string, Array<string>>,
 *   form: Record<string, string>,
 *   missingPasswordRuleKeys: Array<string>,
 *   t: (key: string) => string,
 *   weakPassword: boolean,
 *   onFormChange: import('react').Dispatch<import('react').SetStateAction<Record<string, string>>>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function RegisterPasswordField({
    fieldErrors,
    form,
    missingPasswordRuleKeys,
    t,
    weakPassword,
    onFormChange,
}) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="register-password">
                {t('auth.password')}
            </label>
            <SensitiveInput
                id="register-password"
                value={form.password}
                invalid={Boolean(weakPassword || fieldErrors.password?.[0])}
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
            {fieldErrors.password?.[0] ? (
                <p className="app-field__error">{fieldErrors.password[0]}</p>
            ) : null}
        </div>
    );
}

/**
 * Render the registration password confirmation field.
 *
 * @param {{
 *   fieldErrors: Record<string, Array<string>>,
 *   form: Record<string, string>,
 *   passwordMismatch: boolean,
 *   t: (key: string) => string,
 *   onFormChange: import('react').Dispatch<import('react').SetStateAction<Record<string, string>>>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function RegisterPasswordConfirmationField({
    fieldErrors,
    form,
    passwordMismatch,
    t,
    onFormChange,
}) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="register-password-confirmation">
                {t('auth.confirmPassword')}
            </label>
            <SensitiveInput
                id="register-password-confirmation"
                value={form.password_confirmation}
                invalid={Boolean(passwordMismatch || fieldErrors.password_confirmation?.[0])}
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
            {fieldErrors.password_confirmation?.[0] ? (
                <p className="app-field__error">{fieldErrors.password_confirmation[0]}</p>
            ) : null}
        </div>
    );
}
