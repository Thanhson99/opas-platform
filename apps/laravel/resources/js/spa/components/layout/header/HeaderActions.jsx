import HeaderAccount from './HeaderAccount';
import HeaderUtilityActions from './HeaderUtilityActions';

/**
 * Render utility controls and account actions for the workspace header.
 */
export default function HeaderActions({ loading, user, registerProviders, logout, t }) {
    return (
        <div className="opas-header__actions">
            <HeaderUtilityActions t={t} />
            <HeaderAccount
                loading={loading}
                user={user}
                registerProviders={registerProviders}
                logout={logout}
                t={t}
            />
        </div>
    );
}
