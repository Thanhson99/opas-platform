import { memo, useCallback } from 'react';
import { buildProviderInputName } from './authProviderAdminFieldUtils';

/**
 * Render one standard text/number base provider input.
 */
function AuthProviderStandardBaseInput({
    providerKey,
    field,
    inputId,
    value,
    placeholder,
    errorMessage,
    onFieldBlur,
    onFieldChange,
}) {
    const handleBlur = useCallback(() => {
        onFieldBlur(field);
    }, [field, onFieldBlur]);

    const handleChange = useCallback(
        (event) => onFieldChange(field, event.target.value),
        [field, onFieldChange],
    );

    return (
        <input
            id={inputId}
            className={`app-input ${errorMessage ? 'app-input--invalid' : ''}`}
            type={field === 'sort_order' ? 'number' : 'text'}
            inputMode={field === 'sort_order' ? 'numeric' : undefined}
            min={field === 'sort_order' ? '0' : undefined}
            step={field === 'sort_order' ? '1' : undefined}
            name={buildProviderInputName(providerKey, field)}
            autoComplete="off"
            data-lpignore="true"
            data-1p-ignore="true"
            value={value}
            placeholder={placeholder}
            aria-invalid={Boolean(errorMessage)}
            onBlur={handleBlur}
            onChange={handleChange}
        />
    );
}

export default memo(AuthProviderStandardBaseInput);
