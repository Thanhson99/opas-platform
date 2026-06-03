import { memo } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';
import {
    TelegramBotAvatar,
    TelegramBotBadge,
    TelegramBotDetailRow,
    TelegramBotMetricCard,
} from './TelegramBotUi';
import { resolveBotEnvironmentTone } from './telegramBotAdmin.helpers';

/**
 * Render editable basic identity metadata for a bot.
 *
 * @param {{
 *   t: (key: string) => string,
 *   bot: Record<string, any>,
 *   form: Record<string, any>,
 *   saving: boolean,
 *   hasChanges?: boolean,
 *   showInlineSave?: boolean,
 *   onSave: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export const TelegramBotBasicInfoCard = memo(function TelegramBotBasicInfoCard({
    t,
    bot,
    form,
    saving,
    hasChanges = true,
    showInlineSave = true,
    onSave,
}) {
    const username = form.bot_username || `@${bot.key}`;
    const localeLabel = form.locale === 'vi' ? t('common.vietnamese') : t('common.english');

    return (
        <section className="admin-telegram-bots__card admin-telegram-bots__bot-profile-card">
            <header className="admin-telegram-bots__card-head">
                <div>
                    <h4>{t('adminTelegramBots.sections.basicInfo')}</h4>
                    <p>{t('adminTelegramBots.sections.basicInfoText')}</p>
                </div>
                {showInlineSave ? (
                    <button
                        type="button"
                        className="admin-telegram-bots__inline-action"
                        onClick={onSave}
                        disabled={saving || !hasChanges}
                        title={
                            hasChanges
                                ? t('adminTelegramBots.saveButton')
                                : t('adminTelegramBots.noChangesToSave')
                        }
                    >
                        <AppIcon name="edit" />
                        {saving
                            ? t('adminTelegramBots.saving')
                            : hasChanges
                              ? t('adminTelegramBots.saveButton')
                              : t('adminTelegramBots.noChangesButton')}
                    </button>
                ) : null}
            </header>

            <div className="admin-telegram-bots__bot-profile-hero">
                <TelegramBotAvatar name="bot" tone="primary" />
                <div className="admin-telegram-bots__bot-profile-title">
                    <h5>{form.display_name}</h5>
                    <p>{username}</p>
                    <div className="admin-telegram-bots__badge-row">
                        <TelegramBotBadge tone={resolveBotEnvironmentTone(form.environment)}>
                            {t(`adminTelegramBots.classification.environment.${form.environment}`)}
                        </TelegramBotBadge>
                        <TelegramBotBadge tone="purpose">
                            {t(`adminTelegramBots.classification.purpose.${form.purpose}`)}
                        </TelegramBotBadge>
                        {form.is_default ? (
                            <TelegramBotBadge tone="soft-success">
                                <span className="admin-telegram-bots__badge-dot" />
                                {t('adminTelegramBots.status.defaultShort')}
                            </TelegramBotBadge>
                        ) : null}
                        <TelegramBotBadge tone={form.enabled ? 'success' : 'danger'}>
                            {form.enabled
                                ? t('adminTelegramBots.status.enabled')
                                : t('adminTelegramBots.status.disabled')}
                        </TelegramBotBadge>
                    </div>
                </div>
                <code className="admin-telegram-bots__bot-profile-key">{bot.key}</code>
            </div>

            <div className="admin-telegram-bots__bot-profile-grid">
                <div className="admin-telegram-bots__info-group">
                    <h5>{t('adminTelegramBots.sections.identity')}</h5>
                    <div className="admin-telegram-bots__info-list">
                        <TelegramBotDetailRow label={t('adminTelegramBots.fields.displayName')}>
                            {form.display_name}
                        </TelegramBotDetailRow>
                        <TelegramBotDetailRow label={t('adminTelegramBots.fields.key')}>
                            <code>{bot.key}</code>
                        </TelegramBotDetailRow>
                        <TelegramBotDetailRow label={t('adminTelegramBots.fields.botUsername')}>
                            {username}
                        </TelegramBotDetailRow>
                        <TelegramBotDetailRow label={t('adminTelegramBots.fields.locale')}>
                            {localeLabel}
                        </TelegramBotDetailRow>
                    </div>
                </div>

                <div className="admin-telegram-bots__info-group">
                    <h5>{t('adminTelegramBots.sections.routing')}</h5>
                    <div className="admin-telegram-bots__info-list">
                        <TelegramBotDetailRow label={t('adminTelegramBots.fields.purpose')}>
                            <TelegramBotBadge tone="purpose">
                                {t(`adminTelegramBots.classification.purpose.${form.purpose}`)}
                            </TelegramBotBadge>
                        </TelegramBotDetailRow>
                        <TelegramBotDetailRow label={t('adminTelegramBots.fields.environment')}>
                            <TelegramBotBadge tone={resolveBotEnvironmentTone(form.environment)}>
                                {t(
                                    `adminTelegramBots.classification.environment.${form.environment}`,
                                )}
                            </TelegramBotBadge>
                        </TelegramBotDetailRow>
                        <TelegramBotDetailRow label={t('adminTelegramBots.fields.machineGroup')}>
                            {form.machine_group || '—'}
                        </TelegramBotDetailRow>
                        <TelegramBotDetailRow label={t('adminTelegramBots.fields.description')}>
                            {form.description || '—'}
                        </TelegramBotDetailRow>
                    </div>
                </div>
            </div>
        </section>
    );
});

/**
 * Render access counts and runtime summary for the overview tab.
 *
 * @param {{
 *   t: (key: string) => string,
 *   bot: Record<string, any>,
 *   form: Record<string, any>,
 *   runtimeInfo: Record<string, any> | null,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export const TelegramBotOverviewStats = memo(function TelegramBotOverviewStats({
    t,
    bot,
    form,
    runtimeInfo,
}) {
    return (
        <aside className="admin-telegram-bots__stack">
            <section className="admin-telegram-bots__card">
                <div className="admin-telegram-bots__metric-grid">
                    <TelegramBotMetricCard
                        label={t('adminTelegramBots.metrics.chatsAllowed')}
                        value={form.allowed_chat_ids.length}
                        tone="primary"
                    />
                    <TelegramBotMetricCard
                        label={t('adminTelegramBots.metrics.usersAllowed')}
                        value={form.allowed_user_ids.length}
                        tone="success"
                    />
                    <TelegramBotMetricCard
                        label={t('adminTelegramBots.metrics.actionsAllowed')}
                        value={form.allowed_actions.length}
                        tone="warning"
                    />
                </div>
            </section>

            <section className="admin-telegram-bots__card admin-telegram-bots__runtime-card">
                <div>
                    <span>{t('adminTelegramBots.metrics.runtimeActive')}</span>
                    <strong
                        className={
                            runtimeInfo?.key === bot.key
                                ? 'admin-telegram-bots__runtime-state is-active'
                                : 'admin-telegram-bots__runtime-state is-inactive'
                        }
                    >
                        <span className="admin-telegram-bots__status-dot" />
                        {runtimeInfo?.key === bot.key
                            ? t('adminTelegramBots.runtime.activeNow')
                            : t('adminTelegramBots.runtime.notActiveNow')}
                    </strong>
                </div>
                <TelegramBotDetailRow label={t('adminTelegramBots.fields.chatHistoryLimit')}>
                    {form.chat_history_limit}
                </TelegramBotDetailRow>
                <TelegramBotDetailRow label={t('adminTelegramBots.fields.timelineLimit')}>
                    {form.chat_session_timeline_limit}
                </TelegramBotDetailRow>
                <TelegramBotDetailRow label={t('adminTelegramBots.fields.apiBaseUrl')}>
                    <code>{form.api_base_url || 'https://api.telegram.org'}</code>
                </TelegramBotDetailRow>
            </section>
        </aside>
    );
});

/**
 * Render webhook status and operation shortcuts.
 *
 * @param {{
 *   t: (key: string) => string,
 *   webhookInfo: Record<string, any> | null,
 *   operationsLoading: boolean,
 *   showActions?: boolean,
 *   onRefreshRuntime: () => void,
 *   onRegisterWebhook: () => void,
 *   canRegisterWebhook: boolean,
 *   canDeleteWebhook?: boolean,
 *   onDeleteWebhook: () => void,
 *   onSyncCommands: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export const TelegramBotWebhookOperationsCard = memo(function TelegramBotWebhookOperationsCard({
    t,
    webhookInfo,
    operationsLoading,
    showActions = true,
    onRefreshRuntime,
    onRegisterWebhook,
    canRegisterWebhook,
    canDeleteWebhook = false,
    onDeleteWebhook,
    onSyncCommands,
}) {
    const webhookUrl = webhookInfo?.result?.url || '—';
    const hasWebhookUrl = canDeleteWebhook || Boolean(webhookInfo?.result?.url);
    const webhookStatusLabel =
        webhookInfo?.ok === true
            ? t('adminTelegramBots.runtime.webhookReady')
            : t('adminTelegramBots.runtime.webhookUnknown');
    const lastErrorLabel =
        webhookInfo?.result?.last_error_message || t('adminTelegramBots.runtime.noRecentError');
    const lastDeliveryLabel =
        webhookInfo?.result?.last_synchronization_error_date ||
        t('adminTelegramBots.runtime.noErrorTime');

    return (
        <section className="admin-telegram-bots__card admin-telegram-bots__card--full">
            <header className="admin-telegram-bots__card-head">
                <div>
                    <h4>{t('adminTelegramBots.sections.webhookOperations')}</h4>
                    <p>{t('adminTelegramBots.sections.webhookOperationsText')}</p>
                </div>
            </header>

            <div className="admin-telegram-bots__webhook-box">
                <TelegramBotDetailRow label={t('adminTelegramBots.runtime.webhookUrl')}>
                    <code>{webhookUrl}</code>
                </TelegramBotDetailRow>
                <div className="admin-telegram-bots__webhook-grid">
                    <TelegramBotDetailRow label={t('adminTelegramBots.runtime.webhookStatus')}>
                        <TelegramBotBadge tone={webhookInfo?.ok === true ? 'success' : 'warning'}>
                            {webhookStatusLabel}
                        </TelegramBotBadge>
                    </TelegramBotDetailRow>
                    <TelegramBotDetailRow label={t('adminTelegramBots.runtime.lastError')}>
                        {lastErrorLabel}
                    </TelegramBotDetailRow>
                    <TelegramBotDetailRow label={t('adminTelegramBots.runtime.pendingUpdates')}>
                        {webhookInfo?.result?.pending_update_count ?? 0}
                    </TelegramBotDetailRow>
                    <TelegramBotDetailRow label={t('adminTelegramBots.runtime.lastDelivery')}>
                        {lastDeliveryLabel}
                    </TelegramBotDetailRow>
                </div>
            </div>

            {showActions ? (
                <div className="admin-telegram-bots__operations-grid">
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={onRefreshRuntime}
                        disabled={operationsLoading}
                        title={t('adminTelegramBots.refreshButton')}
                    >
                        <AppIcon name="refresh" />
                        <span>{t('adminTelegramBots.refreshButton')}</span>
                        <small>{t('adminTelegramBots.operationHints.refresh')}</small>
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={onRegisterWebhook}
                        disabled={operationsLoading || !canRegisterWebhook}
                        title={
                            canRegisterWebhook
                                ? t('adminTelegramBots.webhookRegisterButton')
                                : t('adminTelegramBots.operationHints.registerWebhookMissingUrl')
                        }
                    >
                        <AppIcon name="link" />
                        <span>{t('adminTelegramBots.webhookRegisterButton')}</span>
                        <small>
                            {canRegisterWebhook
                                ? t('adminTelegramBots.operationHints.registerWebhook')
                                : t('adminTelegramBots.operationHints.registerWebhookMissingUrl')}
                        </small>
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--danger is-danger"
                        onClick={onDeleteWebhook}
                        disabled={operationsLoading || !hasWebhookUrl}
                        title={
                            hasWebhookUrl
                                ? t('adminTelegramBots.webhookDeleteButton')
                                : t('adminTelegramBots.operationHints.deleteWebhookUnavailable')
                        }
                    >
                        <AppIcon name="trash" />
                        <span>{t('adminTelegramBots.webhookDeleteButton')}</span>
                        <small>
                            {hasWebhookUrl
                                ? t('adminTelegramBots.operationHints.deleteWebhook')
                                : t('adminTelegramBots.operationHints.deleteWebhookUnavailable')}
                        </small>
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={onSyncCommands}
                        disabled={operationsLoading}
                        title={t('adminTelegramBots.syncButton')}
                    >
                        <AppIcon name="terminal" />
                        <span>{t('adminTelegramBots.syncButton')}</span>
                        <small>{t('adminTelegramBots.operationHints.syncCommands')}</small>
                    </button>
                </div>
            ) : null}
        </section>
    );
});
