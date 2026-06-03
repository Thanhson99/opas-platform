import { Link } from 'react-router-dom';
import AuthPanelBrand from './AuthPanelBrand';

/**
 * Render the auth panel header with a login link action.
 *
 * @param {{ t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthLoginLinkHeader({ t }) {
    return (
        <div className="app-auth-panel__topbar">
            <AuthPanelBrand t={t} />
            <div className="app-auth-panel__topbar-actions">
                <Link to="/login" className="app-auth-panel__back-link">
                    {t('auth.goToLogin')}
                </Link>
            </div>
        </div>
    );
}
