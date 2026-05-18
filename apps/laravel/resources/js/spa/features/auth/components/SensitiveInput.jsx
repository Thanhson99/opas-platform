import { useState } from 'react';
import AppIcon from '../../../components/icons/AppIcon';

/**
 * Render a sensitive text input with a show/hide toggle.
 *
 * @param {{
 *   value: string,
 *   onChange?: ((event: import('react').ChangeEvent<HTMLInputElement>) => void) | undefined,
 *   placeholder?: string,
 *   invalid?: boolean,
 *   name?: string,
 *   autoComplete?: string,
 *   readOnly?: boolean,
 *   disabled?: boolean,
 *   required?: boolean,
 *   onBlur?: ((event: import('react').FocusEvent<HTMLInputElement>) => void) | undefined,
 *   revealLabel: string,
 *   concealLabel: string,
 *   className?: string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function SensitiveInput({
    value,
    onChange,
    placeholder = '',
    invalid = false,
    name,
    autoComplete = 'off',
    readOnly = false,
    disabled = false,
    required = false,
    onBlur,
    revealLabel,
    concealLabel,
    className = '',
}) {
    const [revealed, setRevealed] = useState(false);

    return (
        <div className={`app-sensitive-input ${className}`.trim()}>
            <input
                className={`app-input app-sensitive-input__control ${
                    invalid ? 'app-input--invalid' : ''
                }`}
                type={revealed ? 'text' : 'password'}
                name={name}
                autoComplete={autoComplete}
                data-lpignore="true"
                data-1p-ignore="true"
                value={value}
                readOnly={readOnly}
                disabled={disabled}
                required={required}
                placeholder={placeholder}
                onBlur={onBlur}
                onChange={onChange}
            />
            <button
                type="button"
                className="app-sensitive-input__toggle"
                onClick={() => setRevealed((current) => !current)}
                aria-label={revealed ? concealLabel : revealLabel}
                title={revealed ? concealLabel : revealLabel}
            >
                <AppIcon name={revealed ? 'eye-off' : 'eye'} />
            </button>
        </div>
    );
}
