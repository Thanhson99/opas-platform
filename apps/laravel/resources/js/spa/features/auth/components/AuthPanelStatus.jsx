/**
 * Render a compact telemetry-style status row in auth panels.
 *
 * @param {{ text: string }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthPanelStatus({ text }) {
    return (
        <div className="app-auth-panel__status-row" role="status">
            <span className="app-auth-panel__status-dot" aria-hidden="true" />
            <span>{text}</span>
        </div>
    );
}
