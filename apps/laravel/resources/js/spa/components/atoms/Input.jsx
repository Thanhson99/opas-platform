import { joinClassNames } from '../../utils/classNames';

/**
 * Render a labeled token-based text input.
 *
 * @param {import('react').InputHTMLAttributes<HTMLInputElement> & {
 *   label?: string,
 *   error?: string,
 *   helperText?: string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function Input({
    className = '',
    error = '',
    helperText = '',
    id,
    label,
    ...props
}) {
    const inputId = id ?? props.name;
    const helperId = helperText ? `${inputId}-helper` : undefined;
    const errorId = error ? `${inputId}-error` : undefined;

    return (
        <div className="cyber-field">
            {label ? (
                <label className="cyber-field__label" htmlFor={inputId}>
                    {label}
                </label>
            ) : null}
            <input
                aria-describedby={errorId ?? helperId}
                aria-invalid={error ? 'true' : undefined}
                className={joinClassNames('cyber-input', error && 'is-invalid', className)}
                id={inputId}
                {...props}
            />
            {error ? (
                <span className="cyber-field__error" id={errorId}>
                    {error}
                </span>
            ) : null}
            {!error && helperText ? (
                <span className="cyber-field__helper" id={helperId}>
                    {helperText}
                </span>
            ) : null}
        </div>
    );
}
