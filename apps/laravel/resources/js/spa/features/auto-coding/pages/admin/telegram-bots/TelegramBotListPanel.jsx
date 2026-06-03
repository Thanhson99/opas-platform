import { memo, useCallback, useMemo } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';
import { TelegramBotAvatar, TelegramBotBadge, TelegramBotEmptyState } from './TelegramBotUi';
import {
    BOT_ENVIRONMENT_OPTIONS,
    BOT_PURPOSE_OPTIONS,
    paginateBots,
    resolveBotEnvironmentTone,
} from './telegramBotAdmin.helpers';

/**
 * Render the bot master list panel with filters and pagination.
 *
 * @param {{
 *   t: (key: string) => string,
 *   bots: Record<string, any>[],
 *   totalBotCount?: number,
 *   filters: {search: string, environment: string, purpose: string, status: string},
 *   page: number,
 *   onPageChange: (page: number) => void,
 *   onFilterChange: (field: string, value: string) => void,
 *   onRefresh: () => void,
 *   onEdit: (botKey: string) => void,
 *   onActivate: (bot: Record<string, any>) => void,
 *   onDelete: (bot: Record<string, any>) => void,
 *   savingKey?: string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotListPanel({
    t,
    bots,
    totalBotCount = bots.length,
    filters,
    page,
    onPageChange,
    onFilterChange,
    onRefresh,
    onEdit,
    onActivate,
    onDelete,
    savingKey = '',
}) {
    const pagination = useMemo(() => paginateBots(bots, page), [bots, page]);
    const hasActiveFilters = Object.values(filters).some((value) => value !== '');
    const isFilteredEmpty = bots.length === 0 && totalBotCount > 0 && hasActiveFilters;
    const pageNumbers = useMemo(
        () => Array.from({ length: pagination.totalPages }, (_, index) => index + 1),
        [pagination.totalPages],
    );
    const labels = useMemo(
        () => ({
            refresh: t('adminTelegramBots.listRefreshButton'),
            searchPlaceholder: t('adminTelegramBots.searchPlaceholder'),
            filters: {
                environment: t('adminTelegramBots.filters.environment'),
                purpose: t('adminTelegramBots.filters.purpose'),
                status: t('adminTelegramBots.filters.status'),
            },
            columns: {
                bot: t('adminTelegramBots.columns.bot'),
                environment: t('adminTelegramBots.columns.environment'),
                purpose: t('adminTelegramBots.columns.purpose'),
                status: t('adminTelegramBots.columns.status'),
                actions: t('adminTelegramBots.columns.actions'),
            },
            environments: Object.fromEntries(
                BOT_ENVIRONMENT_OPTIONS.map((value) => [
                    value,
                    t(`adminTelegramBots.classification.environment.${value}`),
                ]),
            ),
            purposes: Object.fromEntries(
                BOT_PURPOSE_OPTIONS.map((value) => [
                    value,
                    t(`adminTelegramBots.classification.purpose.${value}`),
                ]),
            ),
            status: {
                enabled: t('adminTelegramBots.status.enabled'),
                disabled: t('adminTelegramBots.status.disabled'),
                defaultBot: t('adminTelegramBots.status.defaultBot'),
                defaultShort: t('adminTelegramBots.status.defaultShort'),
            },
            editButton: t('adminTelegramBots.editButton'),
            activateButton: t('adminTelegramBots.activateBotButton'),
            deactivateButton: t('adminTelegramBots.deactivateBotButton'),
            deleteButton: t('adminTelegramBots.deleteBotButton'),
            emptyTitle: t('adminTelegramBots.emptyTitle'),
            emptyText: t('adminTelegramBots.emptyText'),
            noFilteredResultsTitle: t('adminTelegramBots.noFilteredResultsTitle'),
            noFilteredResultsText: t('adminTelegramBots.noFilteredResultsText'),
            clearFilters: t('adminTelegramBots.clearFilters'),
            filteredCount: t('adminTelegramBots.filteredCount'),
            totalCount: t('adminTelegramBots.totalCount'),
            activeFilters: t('adminTelegramBots.activeFilters'),
            noActiveFilters: t('adminTelegramBots.noActiveFilters'),
            pagination: {
                showing: t('adminTelegramBots.pagination.showing'),
                to: t('adminTelegramBots.pagination.to'),
                of: t('adminTelegramBots.pagination.of'),
                results: t('adminTelegramBots.pagination.results'),
                previous: t('adminTelegramBots.pagination.previous'),
                next: t('adminTelegramBots.pagination.next'),
                page: t('adminTelegramBots.pagination.page'),
            },
        }),
        [t],
    );

    const handleSearchChange = useCallback(
        (event) => onFilterChange('search', event.target.value),
        [onFilterChange],
    );
    const handleEnvironmentChange = useCallback(
        (event) => onFilterChange('environment', event.target.value),
        [onFilterChange],
    );
    const handlePurposeChange = useCallback(
        (event) => onFilterChange('purpose', event.target.value),
        [onFilterChange],
    );
    const handleStatusChange = useCallback(
        (event) => onFilterChange('status', event.target.value),
        [onFilterChange],
    );
    const handleClearFilters = useCallback(() => {
        onFilterChange('search', '');
        onFilterChange('environment', '');
        onFilterChange('purpose', '');
        onFilterChange('status', '');
    }, [onFilterChange]);
    const handlePreviousPage = useCallback(() => {
        onPageChange(Math.max(1, pagination.safePage - 1));
    }, [onPageChange, pagination.safePage]);
    const handleNextPage = useCallback(() => {
        onPageChange(Math.min(pagination.totalPages, pagination.safePage + 1));
    }, [onPageChange, pagination.safePage, pagination.totalPages]);
    const handlePageClick = useCallback(
        (event) => {
            onPageChange(Number(event.currentTarget.dataset.page));
        },
        [onPageChange],
    );

    return (
        <section className="admin-telegram-bots__panel admin-telegram-bots__panel--list">
            <header className="admin-telegram-bots__panel-head">
                <div>
                    <h3>
                        {t('adminTelegramBots.listTitle')}{' '}
                        <span>
                            ({hasActiveFilters ? `${bots.length}/${totalBotCount}` : totalBotCount})
                        </span>
                    </h3>
                    <p>{t('adminTelegramBots.listText')}</p>
                </div>
                <button
                    type="button"
                    className="admin-telegram-bots__icon-button admin-telegram-bots__icon-button--text"
                    onClick={onRefresh}
                    aria-label={labels.refresh}
                    title={labels.refresh}
                >
                    <AppIcon name="refresh" />
                    <span>{labels.refresh}</span>
                </button>
            </header>

            <div className="admin-telegram-bots__filters">
                <label className="admin-telegram-bots__search">
                    <AppIcon name="search" />
                    <input
                        id="telegram-bot-search"
                        className="app-input"
                        type="search"
                        value={filters.search}
                        onChange={handleSearchChange}
                        aria-label={labels.searchPlaceholder}
                        placeholder={labels.searchPlaceholder}
                    />
                </label>

                <div className="admin-telegram-bots__filter-row">
                    <label className="admin-telegram-bots__filter-select">
                        <select
                            id="telegram-bot-environment-filter"
                            value={filters.environment}
                            onChange={handleEnvironmentChange}
                            aria-label={labels.filters.environment}
                        >
                            <option value="">{labels.filters.environment}</option>
                            {BOT_ENVIRONMENT_OPTIONS.map((value) => (
                                <option key={value} value={value}>
                                    {labels.environments[value]}
                                </option>
                            ))}
                        </select>
                        <AppIcon name="chevron-down" />
                    </label>

                    <label className="admin-telegram-bots__filter-select">
                        <select
                            id="telegram-bot-purpose-filter"
                            value={filters.purpose}
                            onChange={handlePurposeChange}
                            aria-label={labels.filters.purpose}
                        >
                            <option value="">{labels.filters.purpose}</option>
                            {BOT_PURPOSE_OPTIONS.map((value) => (
                                <option key={value} value={value}>
                                    {labels.purposes[value]}
                                </option>
                            ))}
                        </select>
                        <AppIcon name="chevron-down" />
                    </label>

                    <label className="admin-telegram-bots__filter-select">
                        <select
                            id="telegram-bot-status-filter"
                            value={filters.status}
                            onChange={handleStatusChange}
                            aria-label={labels.filters.status}
                        >
                            <option value="">{labels.filters.status}</option>
                            <option value="enabled">{labels.status.enabled}</option>
                            <option value="disabled">{labels.status.disabled}</option>
                            <option value="default">{labels.status.defaultBot}</option>
                        </select>
                        <AppIcon name="chevron-down" />
                    </label>

                    {hasActiveFilters ? (
                        <button
                            type="button"
                            className="admin-telegram-bots__filter-clear"
                            onClick={handleClearFilters}
                        >
                            <AppIcon name="x" />
                            {labels.clearFilters}
                        </button>
                    ) : null}
                </div>
                <p className="admin-telegram-bots__filter-summary">
                    {hasActiveFilters
                        ? `${labels.activeFilters}: ${bots.length} ${labels.filteredCount}, ${totalBotCount} ${labels.totalCount}.`
                        : labels.noActiveFilters}
                </p>
            </div>

            <div className="app-table-wrap app-table-wrap--wide admin-telegram-bots__table-card">
                <table className="app-table app-user-admin-table admin-telegram-bots__table">
                    <thead>
                        <tr>
                            <th>{labels.columns.bot}</th>
                            <th>{labels.columns.environment}</th>
                            <th>{labels.columns.purpose}</th>
                            <th>{labels.columns.status}</th>
                            <th>{labels.columns.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pagination.items.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="admin-telegram-bots__table-empty">
                                    <TelegramBotEmptyState
                                        title={
                                            isFilteredEmpty
                                                ? labels.noFilteredResultsTitle
                                                : labels.emptyTitle
                                        }
                                        text={
                                            isFilteredEmpty
                                                ? labels.noFilteredResultsText
                                                : labels.emptyText
                                        }
                                    />
                                </td>
                            </tr>
                        ) : (
                            pagination.items.map((bot) => (
                                <TelegramBotTableRow
                                    key={bot.key}
                                    bot={bot}
                                    labels={labels}
                                    onEdit={onEdit}
                                    onActivate={onActivate}
                                    onDelete={onDelete}
                                    savingKey={savingKey}
                                />
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <footer className="admin-telegram-bots__pagination">
                <span>
                    {labels.pagination.showing} {pagination.firstItem} {labels.pagination.to}{' '}
                    {pagination.lastItem} {labels.pagination.of} {bots.length}{' '}
                    {labels.pagination.results}
                </span>
                <div className="admin-telegram-bots__pagination-actions">
                    <button
                        type="button"
                        className="admin-telegram-bots__page-button"
                        onClick={handlePreviousPage}
                        disabled={pagination.safePage <= 1}
                        aria-label={labels.pagination.previous}
                    >
                        <AppIcon name="chevron-down" />
                    </button>
                    {pageNumbers.map((item) => (
                        <button
                            key={item}
                            type="button"
                            className={`admin-telegram-bots__page-button ${item === pagination.safePage ? 'is-active' : ''}`}
                            data-page={item}
                            onClick={handlePageClick}
                            aria-current={item === pagination.safePage ? 'page' : undefined}
                            aria-label={`${labels.pagination.page} ${item}`}
                        >
                            {item}
                        </button>
                    ))}
                    <button
                        type="button"
                        className="admin-telegram-bots__page-button admin-telegram-bots__page-button--next"
                        onClick={handleNextPage}
                        disabled={pagination.safePage >= pagination.totalPages}
                        aria-label={labels.pagination.next}
                    >
                        <AppIcon name="chevron-down" />
                    </button>
                </div>
            </footer>
        </section>
    );
}

export default memo(TelegramBotListPanel);

const TelegramBotTableRow = memo(function TelegramBotTableRow({
    bot,
    labels,
    onEdit,
    onActivate,
    onDelete,
    savingKey,
}) {
    const handleEdit = useCallback(() => {
        onEdit(bot.key);
    }, [bot.key, onEdit]);
    const handleActivate = useCallback(() => {
        onActivate(bot);
    }, [bot, onActivate]);
    const handleDelete = useCallback(() => {
        onDelete(bot);
    }, [bot, onDelete]);
    const environment = bot.environment ?? 'local';
    const purpose = bot.purpose ?? 'remote_control';
    const isSaving = savingKey === bot.key;
    const editLabel = `${labels.editButton} ${bot.display_name}`;
    const toggleLabel = bot.enabled
        ? `${labels.deactivateButton} ${bot.display_name}`
        : `${labels.activateButton} ${bot.display_name}`;
    const deleteLabel = `${labels.deleteButton} ${bot.display_name}`;

    return (
        <tr>
            <td>
                <span className="admin-telegram-bots__row-main">
                    <TelegramBotAvatar name="bot" tone={bot.enabled ? 'soft' : 'neutral'} />
                    <span className="admin-telegram-bots__row-copy">
                        <strong>{bot.display_name}</strong>
                        <small>{bot.public_config?.bot_username || bot.key}</small>
                    </span>
                </span>
            </td>
            <td>
                <TelegramBotBadge tone={resolveBotEnvironmentTone(environment)}>
                    {labels.environments[environment]}
                </TelegramBotBadge>
            </td>
            <td>
                <TelegramBotBadge tone="purpose">{labels.purposes[purpose]}</TelegramBotBadge>
            </td>
            <td>
                <span className="admin-telegram-bots__status-stack">
                    <TelegramBotBadge tone={bot.enabled ? 'success' : 'danger'}>
                        {bot.enabled ? labels.status.enabled : labels.status.disabled}
                    </TelegramBotBadge>
                    {bot.is_default ? (
                        <TelegramBotBadge tone="soft-success">
                            <span className="admin-telegram-bots__badge-dot" />
                            {labels.status.defaultShort}
                        </TelegramBotBadge>
                    ) : null}
                </span>
            </td>
            <td>
                <div className="app-user-admin-table__actions admin-telegram-bots__table-actions">
                    <button
                        type="button"
                        className={`app-button app-button--ghost app-user-admin-table__action-button admin-telegram-bots__table-toggle-button ${
                            bot.enabled ? 'is-enabled' : 'is-disabled'
                        }`}
                        disabled={isSaving}
                        onClick={handleActivate}
                        title={toggleLabel}
                        aria-label={toggleLabel}
                        aria-pressed={bot.enabled}
                    >
                        <AppIcon name="power" />
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost app-user-admin-table__action-button"
                        disabled={isSaving}
                        onClick={handleEdit}
                        title={editLabel}
                        aria-label={editLabel}
                    >
                        <AppIcon name="edit" />
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost app-user-admin-table__action-button admin-telegram-bots__table-delete-button"
                        disabled={isSaving}
                        onClick={handleDelete}
                        title={deleteLabel}
                        aria-label={deleteLabel}
                    >
                        <AppIcon name="trash" />
                    </button>
                </div>
            </td>
        </tr>
    );
});
