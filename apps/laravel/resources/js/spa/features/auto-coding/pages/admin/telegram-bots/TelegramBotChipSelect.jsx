import { memo, useCallback, useMemo } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';
import { formatOptionLabel, toggleSelection } from './telegramBotAdmin.helpers';

const DANGEROUS_OPTIONS = new Set([
    'cancel_task',
    'cancel_tasks',
    'delete_task',
    'delete_tasks',
    'purge_tasks',
    'reset',
]);

/**
 * Render a polished chip-based multi-select group.
 *
 * @param {{
 *   label: string,
 *   values: string[],
 *   options: string[],
 *   t: (key: string) => string,
 *   optionPrefix?: string,
 *   help?: string,
 *   onChange: (values: string[]) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotChipSelect({
    label,
    values,
    options,
    t,
    optionPrefix = '',
    help = '',
    onChange,
}) {
    const allOptionsSelected = useMemo(
        () => options.length > 0 && options.every((option) => values.includes(option)),
        [options, values],
    );
    const selectedDangerCount = useMemo(
        () => values.filter((option) => DANGEROUS_OPTIONS.has(option)).length,
        [values],
    );

    const handleSelectAll = useCallback(() => {
        onChange(options);
    }, [onChange, options]);

    const handleClearSelection = useCallback(() => {
        onChange([]);
    }, [onChange]);

    const handleToggleOption = useCallback(
        (event) => {
            onChange(toggleSelection(values, event.currentTarget.dataset.option));
        },
        [onChange, values],
    );

    return (
        <div className="admin-telegram-bots__field">
            <span>{label}</span>
            <div className="admin-telegram-bots__chip-summary">
                <span>
                    {values.length}/{options.length} {t('adminTelegramBots.selectionSummary')}
                </span>
                {selectedDangerCount > 0 ? (
                    <strong>
                        <AppIcon name="info" />
                        {selectedDangerCount} {t('adminTelegramBots.dangerSelectionSummary')}
                    </strong>
                ) : (
                    <small>{t('adminTelegramBots.dangerSelectionHelp')}</small>
                )}
            </div>
            <div className="admin-telegram-bots__chip-toolbar">
                <button
                    type="button"
                    className="app-button app-button--ghost"
                    disabled={allOptionsSelected}
                    onClick={handleSelectAll}
                    title={t('adminTelegramBots.selectAll')}
                >
                    <AppIcon name="check" />
                    {t('adminTelegramBots.selectAll')}
                </button>
                <button
                    type="button"
                    className="app-button app-button--ghost"
                    disabled={values.length === 0}
                    onClick={handleClearSelection}
                    title={t('adminTelegramBots.clearSelection')}
                >
                    <AppIcon name="refresh" />
                    {t('adminTelegramBots.clearSelection')}
                </button>
            </div>
            <div className="admin-telegram-bots__chip-grid">
                {options.map((option) => (
                    <button
                        key={option}
                        type="button"
                        className={`admin-telegram-bots__chip ${
                            values.includes(option) ? 'is-selected' : ''
                        } ${DANGEROUS_OPTIONS.has(option) ? 'is-danger' : ''}`}
                        data-option={option}
                        aria-pressed={values.includes(option)}
                        onClick={handleToggleOption}
                    >
                        {optionPrefix ? t(`${optionPrefix}.${option}`) : formatOptionLabel(option)}
                    </button>
                ))}
            </div>
            {help ? <small>{help}</small> : null}
        </div>
    );
}

export default memo(TelegramBotChipSelect);
