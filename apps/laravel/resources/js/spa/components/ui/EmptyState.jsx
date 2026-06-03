import { memo } from 'react';

/**
 * Render a simple empty-state message for data sets with no rows.
 */
function EmptyState({ text = 'No data available.' }) {
    return (
        <div className="app-empty-state" role="status">
            <p>{text}</p>
        </div>
    );
}

export default memo(EmptyState);
