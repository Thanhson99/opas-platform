/**
 * Render hidden username/password fields to keep password managers away from provider secrets.
 */
export default function AuthProviderHiddenAutofillFields() {
    return (
        <>
            <input
                type="text"
                name="fake-admin-username"
                autoComplete="username"
                tabIndex={-1}
                aria-hidden="true"
                className="app-visually-hidden"
            />
            <input
                type="password"
                name="fake-admin-password"
                autoComplete="current-password"
                tabIndex={-1}
                aria-hidden="true"
                className="app-visually-hidden"
            />
        </>
    );
}
