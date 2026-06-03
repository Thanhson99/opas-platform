import { Link } from 'react-router-dom';

/**
 * Render the compact auth panel action strip.
 *
 * @param {{ label: string, linkLabel?: string, to?: string, fallback?: string }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthPanelActionStrip({ label, linkLabel = '', to = '', fallback = '' }) {
    return (
        <div className="app-auth-panel__action-strip">
            <span>{label}</span>
            {to && linkLabel ? (
                <Link to={to} className="app-auth-panel__action-link">
                    [ {linkLabel} ]
                </Link>
            ) : (
                <span className="app-auth-panel__action-muted" aria-live="polite">
                    {fallback}
                </span>
            )}
        </div>
    );
}
