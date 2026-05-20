/**
 * Render a shared loading placeholder with a short status label.
 */
export default function LoadingState({ text = 'Loading...' }) {
    return (
        <div className="app-feedback app-feedback--loading">
            <div className="app-feedback__pulse" />
            <p>{text}</p>
        </div>
    );
}
