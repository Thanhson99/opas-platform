/**
 * Render the heading block used by secondary auth panels.
 *
 * @param {{ eyebrow: string, title: string, text: string }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthPanelIntro({ eyebrow, title, text }) {
    return (
        <header className="app-auth-panel__heading">
            <p className="app-auth-panel__eyebrow">{eyebrow}</p>
            <h2 className="app-auth-panel__title">{title}</h2>
            <p className="app-auth-panel__text">{text}</p>
        </header>
    );
}
