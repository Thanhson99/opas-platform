import ErrorState from '../../../components/ui/ErrorState';

/**
 * Render the forgot-password request form.
 *
 * @param {{
 *   email: string,
 *   submitting: boolean,
 *   flash: string,
 *   error: string,
 *   invalidEmail: boolean,
 *   isValid: boolean,
 *   t: (key: string) => string,
 *   onEmailChange: (email: string) => void,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function ForgotPasswordForm({
    email,
    submitting,
    flash,
    error,
    invalidEmail,
    isValid,
    t,
    onEmailChange,
    onSubmit,
}) {
    return (
        <>
            {flash ? (
                <div className="app-provider-note app-provider-note--success">{flash}</div>
            ) : null}
            {error ? <ErrorState text={error} /> : null}
            <form className="app-form" onSubmit={onSubmit}>
                <div className="app-field">
                    <label className="app-label" htmlFor="forgot-password-email">
                        {t('auth.email')}
                    </label>
                    <input
                        id="forgot-password-email"
                        className={`app-input ${invalidEmail ? 'app-input--invalid' : ''}`}
                        type="email"
                        autoComplete="email"
                        value={email}
                        aria-invalid={invalidEmail}
                        aria-describedby="forgot-password-email-help"
                        onChange={(event) => onEmailChange(event.target.value)}
                        required
                    />
                    {invalidEmail ? (
                        <p className="app-field__error">{t('auth.invalidEmail')}</p>
                    ) : null}
                    <p id="forgot-password-email-help" className="app-field__help">
                        {t('auth.forgotPasswordEmailHelp')}
                    </p>
                </div>
                <button
                    className="app-button app-button--primary"
                    type="submit"
                    disabled={!isValid || submitting}
                >
                    {submitting ? t('auth.forgotPasswordSending') : t('auth.forgotPasswordSubmit')}
                </button>
            </form>
        </>
    );
}
