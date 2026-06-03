/**
 * Render decorative corner brackets for cyber auth cards.
 *
 * @returns {import('react').JSX.Element}
 */
export default function AuthCardCorners() {
    return (
        <>
            <span className="app-auth-login-card__corner app-auth-login-card__corner--top-left" />
            <span className="app-auth-login-card__corner app-auth-login-card__corner--top-right" />
            <span className="app-auth-login-card__corner app-auth-login-card__corner--bottom-left" />
            <span className="app-auth-login-card__corner app-auth-login-card__corner--bottom-right" />
        </>
    );
}
