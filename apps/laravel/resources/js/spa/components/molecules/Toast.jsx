import { memo } from 'react';
import AppIcon from '../icons/AppIcon';
import { joinClassNames } from '../../utils/classNames';

/**
 * Render a compact notification message.
 *
 * @param {{
 *   title: string,
 *   message?: string,
 *   tone?: 'info' | 'success' | 'warning' | 'danger',
 *   onDismiss?: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function Toast({ message = '', onDismiss, title, tone = 'info' }) {
    return (
        <section className={joinClassNames('cyber-toast', `cyber-toast--${tone}`)} role="status">
            <div className="cyber-toast__content">
                <strong>{title}</strong>
                {message ? <p>{message}</p> : null}
            </div>
            {onDismiss ? (
                <button aria-label="Dismiss notification" type="button" onClick={onDismiss}>
                    <AppIcon name="x" />
                </button>
            ) : null}
        </section>
    );
}

export default memo(Toast);
