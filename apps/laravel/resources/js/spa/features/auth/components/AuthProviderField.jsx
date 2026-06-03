import { memo } from 'react';

/**
 * Render one labeled auth-provider field with shared help and error states.
 *
 * @param {{
 *   label: string,
 *   inputId?: string,
 *   required?: boolean,
 *   description: string,
 *   error?: string,
 *   badge?: import('react').ReactNode,
 *   span?: string,
 *   className?: string,
 *   children: import('react').ReactNode,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AuthProviderField({
    label,
    inputId,
    required = false,
    description,
    error = '',
    badge = null,
    span = 'half',
    className = '',
    children,
}) {
    const labelContent = (
        <>
            {label}
            {required ? <span className="app-label__required">*</span> : null}
            {badge}
        </>
    );

    return (
        <div className={`app-field app-field--${span} ${className}`.trim()}>
            {inputId ? (
                <label className="app-label" htmlFor={inputId}>
                    {labelContent}
                </label>
            ) : (
                <div className="app-label">{labelContent}</div>
            )}
            {children}
            {error ? (
                <p className="app-field__error" role="alert">
                    {error}
                </p>
            ) : null}
            <p className="app-field__help">{description}</p>
        </div>
    );
}

export default memo(AuthProviderField);
