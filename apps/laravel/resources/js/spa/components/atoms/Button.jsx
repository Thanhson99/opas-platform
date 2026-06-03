import { joinClassNames } from '../../utils/classNames';

const variantClassNames = {
    primary: 'cyber-button cyber-button--primary',
    secondary: 'cyber-button cyber-button--secondary',
    ghost: 'cyber-button cyber-button--ghost',
    danger: 'cyber-button cyber-button--danger',
    link: 'cyber-button cyber-button--link',
};

const sizeClassNames = {
    sm: 'cyber-button--sm',
    md: 'cyber-button--md',
    lg: 'cyber-button--lg',
};

/**
 * Render a token-based action button.
 *
 * @param {import('react').ButtonHTMLAttributes<HTMLButtonElement> & {
 *   variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'link',
 *   size?: 'sm' | 'md' | 'lg',
 *   loading?: boolean,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function Button({
    children,
    className = '',
    disabled = false,
    loading = false,
    size = 'md',
    type = 'button',
    variant = 'primary',
    ...props
}) {
    return (
        <button
            className={joinClassNames(
                variantClassNames[variant],
                sizeClassNames[size],
                loading && 'is-loading',
                className,
            )}
            disabled={disabled || loading}
            type={type}
            {...props}
        >
            {loading ? <span className="cyber-button__spinner" aria-hidden="true" /> : null}
            <span>{children}</span>
        </button>
    );
}
