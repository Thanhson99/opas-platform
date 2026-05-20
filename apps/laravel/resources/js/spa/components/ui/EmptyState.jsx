/**
 * Render a simple empty-state message for data sets with no rows.
 */
export default function EmptyState({ text = 'No data available.' }) {
    return (
        <div className="app-empty-state">
            <p>{text}</p>
        </div>
    );
}
