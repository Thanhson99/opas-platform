import { Link } from 'react-router-dom';
import AuthPanelBrand from './AuthPanelBrand';

/**
 * Render the shared auth panel brand header.
 *
 * @param {{ t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthPanelHeader({ t }) {
    return (
        <div className="app-auth-panel__topbar">
            <AuthPanelBrand t={t} />
            <div className="app-auth-panel__topbar-actions">
                <Link to="/" className="app-auth-panel__back-link">
                    {t('auth.backHome')}
                </Link>
            </div>
        </div>
    );
}
