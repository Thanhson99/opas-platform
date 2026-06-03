import { memo, useCallback } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';

/**
 * Render the runtime tab for webhook operations and raw payload inspection.
 *
 * @param {{
 *   t: (key: string) => string,
 *   runtimeInfo: Record<string, any> | null,
 *   webhookInfo: Record<string, any> | null,
 *   webhookForm: {url: string, drop_pending_updates: boolean},
 *   operationsLoading: boolean,
 *   onWebhookFormChange: (field: string, value: any) => void,
 *   onRefreshRuntime: () => void,
 *   onRegisterWebhook: () => void,
 *   onDeleteWebhook: () => void,
 *   onSyncCommands: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotRuntimeTab({
    t,
    runtimeInfo,
    webhookInfo,
    webhookForm,
    operationsLoading,
    onWebhookFormChange,
    onRefreshRuntime,
    onRegisterWebhook,
    onDeleteWebhook,
    onSyncCommands,
}) {
    const handleWebhookUrlChange = useCallback(
        (event) => onWebhookFormChange('url', event.target.value),
        [onWebhookFormChange],
    );
    const handleDropPendingUpdatesChange = useCallback(
        (event) => onWebhookFormChange('drop_pending_updates', event.target.checked),
        [onWebhookFormChange],
    );
    const hasWebhookUrl = Boolean(webhookInfo?.result?.url);
    const canRegisterWebhook = webhookForm.url.trim() !== '';

    return (
        <div className="admin-telegram-bots__stack">
            <section className="admin-telegram-bots__card">
                <header className="admin-telegram-bots__card-head">
                    <div>
                        <h4>{t('adminTelegramBots.runtimeTitle')}</h4>
                        <p>{t('adminTelegramBots.runtimeText')}</p>
                    </div>
                </header>
                <div className="admin-telegram-bots__runtime-list">
                    <div>
                        <span>{t('adminTelegramBots.runtime.bot')}</span>
                        <strong>{runtimeInfo?.display_name ?? '—'}</strong>
                    </div>
                    <div>
                        <span>{t('adminTelegramBots.runtime.locale')}</span>
                        <strong>{runtimeInfo?.locale ?? '—'}</strong>
                    </div>
                    <div>
                        <span>{t('adminTelegramBots.runtime.enabled')}</span>
                        <strong>
                            {runtimeInfo?.enabled
                                ? t('adminTelegramBots.status.enabled')
                                : t('adminTelegramBots.status.disabled')}
                        </strong>
                    </div>
                </div>
            </section>

            <section className="admin-telegram-bots__card">
                <header className="admin-telegram-bots__card-head">
                    <div>
                        <h4>{t('adminTelegramBots.sections.webhookOperations')}</h4>
                        <p>{t('adminTelegramBots.sections.webhookOperationsText')}</p>
                    </div>
                </header>
                <div className="admin-telegram-bots__detail-grid">
                    <label className="admin-telegram-bots__field admin-telegram-bots__field--full">
                        <span>{t('adminTelegramBots.fields.webhookUrl')}</span>
                        <input
                            id="telegram-bot-webhook-url"
                            className="app-input"
                            type="url"
                            value={webhookForm.url}
                            onChange={handleWebhookUrlChange}
                            placeholder={t('adminTelegramBots.placeholders.webhookUrl')}
                        />
                        <small>{t('adminTelegramBots.fieldHelp.webhookUrl')}</small>
                    </label>
                    <label className="admin-telegram-bots__checkbox-card">
                        <input
                            id="telegram-bot-webhook-drop-pending"
                            type="checkbox"
                            checked={webhookForm.drop_pending_updates}
                            onChange={handleDropPendingUpdatesChange}
                        />
                        <span>
                            <strong>{t('adminTelegramBots.fields.dropPendingUpdates')}</strong>
                            <small>{t('adminTelegramBots.fieldHelp.dropPendingUpdates')}</small>
                        </span>
                    </label>
                </div>
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
                        <AppIcon name="arrow-right" />
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
                <details className="admin-telegram-bots__details">
                    <summary>
                        <AppIcon name="terminal" />
                        {t('adminTelegramBots.showRawPayload')}
                    </summary>
                    <pre className="admin-telegram-bots__json">
                        {JSON.stringify(webhookInfo ?? {}, null, 2)}
                    </pre>
                </details>
            </section>
        </div>
    );
}

export default memo(TelegramBotRuntimeTab);
