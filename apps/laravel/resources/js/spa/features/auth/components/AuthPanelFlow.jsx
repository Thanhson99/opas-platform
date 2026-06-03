import LoginFlowDiagram from './LoginFlowDiagram';

/**
 * Render a compact auth flow header for non-login auth cards.
 *
 * @param {{ label: string }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthPanelFlow({ label }) {
    return (
        <div className="app-auth-panel-flow">
            <LoginFlowDiagram className="app-auth-panel-flow__diagram" label={label} />
        </div>
    );
}
