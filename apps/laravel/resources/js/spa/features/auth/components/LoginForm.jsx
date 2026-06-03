import { Link } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import SensitiveInput from './SensitiveInput';

/**
 * Render the email/password login form.
 *
 * @param {{
 *   form: { email: string, password: string },
 *   fieldErrors: Record<string, Array<string>>,
 *   flash: string,
 *   error: string,
 *   invalidEmail: boolean,
 *   canSubmit: boolean,
 *   submitting: boolean,
 *   t: (key: string) => string,
 *   onFormChange: import('react').Dispatch<import('react').SetStateAction<{ email: string, password: string }>>,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function LoginForm({
    form,
    fieldErrors,
    flash,
    error,
    invalidEmail,
    canSubmit,
    submitting,
    t,
    onFormChange,
    onSubmit,
}) {
    return (
        <form className="app-form" onSubmit={onSubmit}>
            <LoginEmailField
                fieldErrors={fieldErrors}
                form={form}
                invalidEmail={invalidEmail}
                t={t}
                onFormChange={onFormChange}
            />
            <LoginPasswordField
                error={error}
                fieldErrors={fieldErrors}
                form={form}
                t={t}
                onFormChange={onFormChange}
            />
            {flash ? (
                <div className="app-provider-note app-provider-note--success" aria-live="polite">
                    {flash}
                </div>
            ) : null}
            {error ? <ErrorState text={error} /> : null}
            <button className="app-button app-button--primary" type="submit" disabled={!canSubmit}>
                {submitting ? t('auth.loginSubmitting') : t('auth.loginSubmit')}
            </button>
            <Link to="/forgot-password" className="app-auth-form-link">
                [ {t('auth.forgotPasswordLink')} ]
            </Link>
        </form>
    );
}

function LoginEmailField({ fieldErrors, form, invalidEmail, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="login-email">
                {t('auth.email')}
            </label>
            <input
                id="login-email"
                className={`app-input ${
                    invalidEmail || fieldErrors.email?.[0] ? 'app-input--invalid' : ''
                }`}
                type="email"
                autoComplete="email"
                value={form.email}
                aria-invalid={Boolean(invalidEmail || fieldErrors.email?.[0])}
                aria-describedby="login-email-help"
                onChange={(event) =>
                    onFormChange((value) => ({
                        ...value,
                        email: event.target.value,
                    }))
                }
                required
            />
            {invalidEmail ? <p className="app-field__error">{t('auth.invalidEmail')}</p> : null}
            {fieldErrors.email?.[0] ? (
                <p className="app-field__error">{fieldErrors.email[0]}</p>
            ) : null}
            <p id="login-email-help" className="app-field__help">
                {t('auth.loginEmailHelp')}
            </p>
        </div>
    );
}

function LoginPasswordField({ error, fieldErrors, form, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="login-password">
                {t('auth.password')}
            </label>
            <SensitiveInput
                id="login-password"
                value={form.password}
                invalid={Boolean(fieldErrors.password?.[0] || error)}
                required
                autoComplete="current-password"
                revealLabel={t('auth.showValue')}
                concealLabel={t('auth.hideValue')}
                onChange={(event) =>
                    onFormChange((value) => ({
                        ...value,
                        password: event.target.value,
                    }))
                }
            />
            {fieldErrors.password?.[0] ? (
                <p className="app-field__error">{fieldErrors.password[0]}</p>
            ) : null}
        </div>
    );
}
