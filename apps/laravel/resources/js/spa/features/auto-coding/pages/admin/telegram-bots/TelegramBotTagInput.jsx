import { memo, useCallback, useId, useState } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';

/**
 * Render a tokenized list input for chat IDs or user IDs.
 *
 * @param {{
 *   label: string,
 *   values: string[],
 *   placeholder: string,
 *   addLabel?: string,
 *   help?: string,
 *   onChange: (values: string[]) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotTagInput({
    label,
    values,
    placeholder,
    addLabel = 'Add',
    help = '',
    onChange,
}) {
    const inputId = useId();
    const [draft, setDraft] = useState('');

    const commitDraft = useCallback(() => {
        const nextValues = draft
            .split(/[,\n]/g)
            .map((value) => value.trim())
            .filter(Boolean);

        if (nextValues.length === 0) {
            setDraft('');

            return;
        }

        onChange(
            [...values, ...nextValues].filter(
                (value, index, array) => array.indexOf(value) === index,
            ),
        );
        setDraft('');
    }, [draft, onChange, values]);

    const handleDraftChange = useCallback((event) => {
        setDraft(event.target.value);
    }, []);

    const handleDraftKeyDown = useCallback(
        (event) => {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                commitDraft();
            }
        },
        [commitDraft],
    );

    const handleRemoveTag = useCallback(
        (event) => {
            const valueToRemove = event.currentTarget.dataset.value;
            onChange(values.filter((item) => item !== valueToRemove));
        },
        [onChange, values],
    );

    return (
        <label className="admin-telegram-bots__field">
            <span>{label}</span>
            <div className="admin-telegram-bots__tag-input">
                <div className="admin-telegram-bots__tag-list">
                    {values.map((value) => (
                        <span key={value} className="admin-telegram-bots__tag">
                            <span>{value}</span>
                            <button
                                type="button"
                                className="admin-telegram-bots__tag-remove"
                                data-value={value}
                                onClick={handleRemoveTag}
                                aria-label={`${label}: ${value}`}
                                title={`${label}: ${value}`}
                            >
                                <AppIcon name="trash" />
                            </button>
                        </span>
                    ))}
                </div>
                <div className="admin-telegram-bots__tag-compose">
                    <input
                        id={inputId}
                        className="app-input"
                        type="text"
                        value={draft}
                        onChange={handleDraftChange}
                        onKeyDown={handleDraftKeyDown}
                        onBlur={commitDraft}
                        placeholder={placeholder}
                    />
                    <button
                        type="button"
                        className="app-button app-button--ghost admin-telegram-bots__tag-add"
                        disabled={draft.trim() === ''}
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={commitDraft}
                    >
                        <AppIcon name="plus" />
                        {addLabel}
                    </button>
                </div>
            </div>
            {help ? <small>{help}</small> : null}
        </label>
    );
}

export default memo(TelegramBotTagInput);
