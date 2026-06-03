import { Link } from 'react-router-dom';

/**
 * Render guest login/register actions in the workspace header.
 */
export default function GuestAccountActions({ registerProviders, t }) {
    return (
        <div className="opas-account opas-account--guest">
            <div className="opas-account__actions">
                <Link to="/login" className="opas-account__action opas-account__action--ghost">
                    {t('common.login')}
                </Link>
                {registerProviders.length > 0 ? (
                    <Link
                        to="/register"
                        className="opas-account__action opas-account__action--primary"
                    >
                        {t('common.register')}
                    </Link>
                ) : null}
            </div>
        </div>
    );
}
