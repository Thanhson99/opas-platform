import ErrorState from '../../../components/ui/ErrorState';

/**
 * Render the email verification code form.
 *
 * @param {{
 *   email: string,
 *   code: string,
 *   verifying: boolean,
 *   resending: boolean,
 *   flash: string,
 *   error: string,
 *   canVerify: boolean,
 *   canResend: boolean,
 *   t: (key: string) => string,
 *   onEmailChange: (email: string) => void,
 *   onCodeChange: (code: string) => void,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 *   onResend: () => Promise<void>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function VerifyEmailForm({
    email,
    code,
    verifying,
    resending,
    flash,
    error,
    canVerify,
    canResend,
    t,
    onEmailChange,
    onCodeChange,
    onSubmit,
    onResend,
}) {
    return (
        <>
            {flash ? (
                <div className="app-provider-note app-provider-note--success">{flash}</div>
            ) : null}
            {error ? <ErrorState text={error} /> : null}
            <form className="app-form" onSubmit={onSubmit}>
                <div className="app-field">
                    <label className="app-label" htmlFor="verify-email-address">
                        {t('auth.email')}
                    </label>
                    <input
                        id="verify-email-address"
                        className="app-input"
                        type="email"
                        autoComplete="email"
                        value={email}
                        onChange={(event) => onEmailChange(event.target.value)}
                        required
                    />
                </div>
                <div className="app-field">
                    <label className="app-label" htmlFor="verify-email-code">
                        {t('auth.verifyEmailCode')}
                    </label>
                    <input
                        id="verify-email-code"
                        className="app-input"
                        type="text"
                        autoComplete="one-time-code"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        maxLength={6}
                        value={code}
                        onChange={(event) =>
                            onCodeChange(event.target.value.replace(/\D/g, '').slice(0, 6))
                        }
                        required
                    />
                    <p className="app-field__hint">{t('auth.verifyEmailCodeHint')}</p>
                </div>
                <button
                    className="app-button app-button--primary"
                    type="submit"
                    disabled={!canVerify}
                >
                    {verifying ? t('auth.verifyEmailVerifying') : t('auth.verifyEmailSubmit')}
                </button>
                <button
                    className="app-button app-button--secondary"
                    type="button"
                    onClick={onResend}
                    disabled={!canResend}
                >
                    {resending ? t('auth.verifyEmailSending') : t('auth.verifyEmailSend')}
                </button>
            </form>
        </>
    );
}
