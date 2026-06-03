import { memo, useCallback, useState } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';
import { TelegramBotAvatar, TelegramBotBadge } from './TelegramBotUi';
import { resolveBotEnvironmentTone } from './telegramBotAdmin.helpers';

/**
 * Render the selected Telegram bot identity and action menu.
 *
 * @param {{
 *   t: (key: string) => string,
 *   bot: Record<string, any>,
 *   form: Record<string, any>,
 *   compact?: boolean,
 *   saving: boolean,
 *   hasChanges?: boolean,
 *   onSave: () => void,
 *   onRefreshRuntime: () => void,
 *   onRegisterWebhook: () => void,
 *   canRegisterWebhook: boolean,
 *   canDeleteWebhook?: boolean,
 *   onDeleteWebhook: () => void,
 *   onSyncCommands: () => void,
 *   onDeleteBot: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotDetailHeader({
    t,
    bot,
    form,
    compact = false,
    saving,
    hasChanges = true,
    onSave,
    onRefreshRuntime,
    onRegisterWebhook,
    canRegisterWebhook,
    canDeleteWebhook = true,
    onDeleteWebhook,
    onSyncCommands,
    onDeleteBot,
}) {
    const [actionsOpen, setActionsOpen] = useState(false);
    const runMenuAction = useCallback((callback) => {
        setActionsOpen(false);
        callback();
    }, []);
    const handleRefreshRuntime = useCallback(
        () => runMenuAction(onRefreshRuntime),
        [onRefreshRuntime, runMenuAction],
    );
    const handleSyncCommands = useCallback(
        () => runMenuAction(onSyncCommands),
        [onSyncCommands, runMenuAction],
    );
    const handleRegisterWebhook = useCallback(
        () => runMenuAction(onRegisterWebhook),
        [onRegisterWebhook, runMenuAction],
    );
    const handleDeleteWebhook = useCallback(
        () => runMenuAction(onDeleteWebhook),
        [onDeleteWebhook, runMenuAction],
    );
    const handleDeleteBot = useCallback(
        () => runMenuAction(onDeleteBot),
        [onDeleteBot, runMenuAction],
    );
    const handleMenuToggle = useCallback((event) => {
        setActionsOpen(event.currentTarget.open);
    }, []);
    const handleMenuKeyDown = useCallback((event) => {
        if (event.key === 'Escape') {
            setActionsOpen(false);
        }
    }, []);
    const actionsMenuLabel = t('adminTelegramBots.actionsMenu');

    return (
        <header
            className={`admin-telegram-bots__detail-head${compact ? ' is-compact' : ''}`}
            aria-label={actionsMenuLabel}
        >
            {compact ? null : (
                <div className="admin-telegram-bots__identity">
                    <TelegramBotAvatar name="bot" tone="primary" />
                    <div className="admin-telegram-bots__identity-copy">
                        <h3>{bot.display_name}</h3>
                        <p>{form.bot_username || `@${bot.key}`}</p>
                        <div className="admin-telegram-bots__badge-row">
                            <TelegramBotBadge tone={resolveBotEnvironmentTone(bot.environment)}>
                                {t(
                                    `adminTelegramBots.classification.environment.${bot.environment ?? 'local'}`,
                                )}
                            </TelegramBotBadge>
                            <TelegramBotBadge tone="purpose">
                                {t(
                                    `adminTelegramBots.classification.purpose.${bot.purpose ?? 'remote_control'}`,
                                )}
                            </TelegramBotBadge>
                            {bot.is_default ? (
                                <TelegramBotBadge tone="soft-success">
                                    <span className="admin-telegram-bots__badge-dot" />
                                    {t('adminTelegramBots.status.defaultShort')}
                                </TelegramBotBadge>
                            ) : null}
                            <TelegramBotBadge tone={bot.enabled ? 'success' : 'danger'}>
                                {bot.enabled
                                    ? t('adminTelegramBots.status.enabled')
                                    : t('adminTelegramBots.status.disabled')}
                            </TelegramBotBadge>
                        </div>
                    </div>
                </div>
            )}

            <div className="admin-telegram-bots__head-actions">
                {compact ? (
                    <span className="admin-telegram-bots__quick-actions-label">
                        <AppIcon name="activity" />
                        {t('adminTelegramBots.quickActions')}
                    </span>
                ) : null}
                <button
                    type="button"
                    className="app-button app-button--primary"
                    onClick={onSave}
                    disabled={saving || !hasChanges}
                    title={
                        hasChanges
                            ? t('adminTelegramBots.saveButton')
                            : t('adminTelegramBots.noChangesToSave')
                    }
                >
                    <AppIcon name="check" />
                    {saving
                        ? t('adminTelegramBots.saving')
                        : hasChanges
                          ? t('adminTelegramBots.saveButton')
                          : t('adminTelegramBots.noChangesButton')}
                </button>
                <details
                    className="admin-telegram-bots__menu"
                    open={actionsOpen}
                    onKeyDown={handleMenuKeyDown}
                    onToggle={handleMenuToggle}
                >
                    <summary aria-label={actionsMenuLabel} title={actionsMenuLabel}>
                        {actionsMenuLabel}
                        <AppIcon name="chevron-down" />
                    </summary>
                    <div className="admin-telegram-bots__menu-list">
                        <button
                            type="button"
                            onClick={handleRefreshRuntime}
                            title={t('adminTelegramBots.refreshButton')}
                        >
                            <AppIcon name="refresh" />
                            <span className="admin-telegram-bots__menu-copy">
                                <span>{t('adminTelegramBots.refreshButton')}</span>
                                <small>{t('adminTelegramBots.operationHints.refresh')}</small>
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={handleSyncCommands}
                            title={t('adminTelegramBots.syncButton')}
                        >
                            <AppIcon name="terminal" />
                            <span className="admin-telegram-bots__menu-copy">
                                <span>{t('adminTelegramBots.syncButton')}</span>
                                <small>{t('adminTelegramBots.operationHints.syncCommands')}</small>
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={handleRegisterWebhook}
                            disabled={!canRegisterWebhook}
                            title={
                                canRegisterWebhook
                                    ? t('adminTelegramBots.webhookRegisterButton')
                                    : t(
                                          'adminTelegramBots.operationHints.registerWebhookMissingUrl',
                                      )
                            }
                        >
                            <AppIcon name="arrow-right" />
                            <span className="admin-telegram-bots__menu-copy">
                                <span>{t('adminTelegramBots.webhookRegisterButton')}</span>
                                <small>
                                    {canRegisterWebhook
                                        ? t('adminTelegramBots.operationHints.registerWebhook')
                                        : t(
                                              'adminTelegramBots.operationHints.registerWebhookMissingUrl',
                                          )}
                                </small>
                            </span>
                        </button>
                        <button
                            type="button"
                            className="is-danger"
                            onClick={handleDeleteWebhook}
                            disabled={!canDeleteWebhook}
                            title={
                                canDeleteWebhook
                                    ? t('adminTelegramBots.webhookDeleteButton')
                                    : t('adminTelegramBots.operationHints.deleteWebhookUnavailable')
                            }
                        >
                            <AppIcon name="trash" />
                            <span className="admin-telegram-bots__menu-copy">
                                <span>{t('adminTelegramBots.webhookDeleteButton')}</span>
                                <small>
                                    {canDeleteWebhook
                                        ? t('adminTelegramBots.operationHints.deleteWebhook')
                                        : t(
                                              'adminTelegramBots.operationHints.deleteWebhookUnavailable',
                                          )}
                                </small>
                            </span>
                        </button>
                        <button
                            type="button"
                            className="is-danger"
                            onClick={handleDeleteBot}
                            title={t('adminTelegramBots.deleteBotButton')}
                        >
                            <AppIcon name="trash" />
                            <span className="admin-telegram-bots__menu-copy">
                                <span>{t('adminTelegramBots.deleteBotButton')}</span>
                                <small>{t('adminTelegramBots.operationHints.deleteBot')}</small>
                            </span>
                        </button>
                    </div>
                </details>
            </div>
        </header>
    );
}

export default memo(TelegramBotDetailHeader);
