import { Link } from 'react-router-dom';

/**
 * Render the registration name field.
 *
 * @param {{
 *   fieldErrors: Record<string, Array<string>>,
 *   form: Record<string, string>,
 *   t: (key: string) => string,
 *   onFormChange: import('react').Dispatch<import('react').SetStateAction<Record<string, string>>>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function RegisterNameField({ fieldErrors, form, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="register-name">
                {t('auth.name')}
            </label>
            <input
                id="register-name"
                className={`app-input ${fieldErrors.name?.[0] ? 'app-input--invalid' : ''}`}
                autoComplete="name"
                value={form.name}
                aria-invalid={Boolean(fieldErrors.name?.[0])}
                aria-describedby="register-name-help"
                onChange={(event) =>
                    onFormChange((value) => ({
                        ...value,
                        name: event.target.value,
                    }))
                }
                required
            />
            {fieldErrors.name?.[0] ? (
                <p className="app-field__error">{fieldErrors.name[0]}</p>
            ) : null}
            <p id="register-name-help" className="app-field__help">
                {t('auth.registerNameHelp')}
            </p>
        </div>
    );
}

/**
 * Render the registration email field.
 *
 * @param {{
 *   fieldErrors: Record<string, Array<string>>,
 *   form: Record<string, string>,
 *   invalidEmail: boolean,
 *   t: (key: string) => string,
 *   onFormChange: import('react').Dispatch<import('react').SetStateAction<Record<string, string>>>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function RegisterEmailField({ fieldErrors, form, invalidEmail, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="register-email">
                {t('auth.email')}
            </label>
            <input
                id="register-email"
                className={`app-input ${
                    invalidEmail || fieldErrors.email?.[0] ? 'app-input--invalid' : ''
                }`}
                type="email"
                autoComplete="email"
                value={form.email}
                aria-invalid={Boolean(invalidEmail || fieldErrors.email?.[0])}
                aria-describedby="register-email-help"
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
            {fieldErrors.email?.[0] ? (
                <p className="app-field__hint">
                    <Link to="/login" className="app-inline-link">
                        {t('auth.goToLogin')}
                    </Link>
                </p>
            ) : null}
            <p id="register-email-help" className="app-field__help">
                {t('auth.registerEmailHelp')}
            </p>
        </div>
    );
}
