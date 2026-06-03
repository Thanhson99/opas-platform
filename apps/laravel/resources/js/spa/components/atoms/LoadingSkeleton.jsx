import { joinClassNames } from '../../utils/classNames';

/**
 * Render a stable skeleton placeholder.
 *
 * @param {{ variant?: 'line' | 'card' | 'table', rows?: number, className?: string }} props
 * @returns {import('react').JSX.Element}
 */
export default function LoadingSkeleton({ className = '', rows = 3, variant = 'line' }) {
    return (
        <div
            className={joinClassNames('cyber-skeleton', `cyber-skeleton--${variant}`, className)}
            aria-hidden="true"
        >
            {Array.from({ length: rows }, (_, index) => (
                <span className="cyber-skeleton__row" key={index} />
            ))}
        </div>
    );
}
