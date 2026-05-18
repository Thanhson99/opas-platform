export default function AuthProviderField({
    label,
    required = false,
    description,
    error = '',
    badge = null,
    span = 'half',
    className = '',
    children,
}) {
    return (
        <div className={`app-field app-field--${span} ${className}`.trim()}>
            <label className="app-label">
                {label}
                {required ? <span className="app-label__required">*</span> : null}
                {badge}
            </label>
            {children}
            {error ? <p className="app-field__error">{error}</p> : null}
            <p className="app-field__help">{description}</p>
        </div>
    );
}
