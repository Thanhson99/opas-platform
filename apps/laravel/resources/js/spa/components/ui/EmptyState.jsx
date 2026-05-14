export default function EmptyState({ text = 'No data available.' }) {
    return (
        <div className="app-empty-state">
            <p>{text}</p>
        </div>
    );
}
