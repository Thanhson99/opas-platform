import { memo } from 'react';

/**
 * Render a shared error message surface.
 */
function ErrorState({ text = 'Something went wrong.' }) {
    return (
        <div className="app-feedback app-feedback--error" role="alert">
            <p>{text}</p>
        </div>
    );
}

export default memo(ErrorState);
