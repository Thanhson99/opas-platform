/**
 * Render the primary login heading without extra runtime data.
 *
 * @param {{ t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function LoginCardTitle({ t }) {
    return (
        <header className="app-auth-login-card__header">
            <p className="app-auth-login-card__eyebrow">{t('auth.loginEyebrow')}</p>
            <h1>OPAS</h1>
            <p>{t('auth.loginTitle')}</p>
        </header>
    );
}
