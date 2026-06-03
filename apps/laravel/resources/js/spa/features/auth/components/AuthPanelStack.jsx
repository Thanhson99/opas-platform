const stackItems = ['Laravel', 'n8n', 'Ollama', 'Postgres', 'Docker'];

/**
 * Render the auth system stack strip adapted from the source template.
 *
 * @returns {import('react').JSX.Element}
 */
export default function AuthPanelStack() {
    return (
        <div className="app-auth-panel__stack" aria-hidden="true">
            [ {stackItems.join(' · ')} ]
        </div>
    );
}
