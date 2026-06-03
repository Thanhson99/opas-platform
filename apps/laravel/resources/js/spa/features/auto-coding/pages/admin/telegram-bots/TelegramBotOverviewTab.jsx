import { memo } from 'react';
import {
    TelegramBotBasicInfoCard,
    TelegramBotOverviewStats,
    TelegramBotWebhookOperationsCard,
} from './TelegramBotOverviewSections';

/**
 * Render the overview tab with basic info, runtime metrics, and webhook snapshot.
 *
 * @param {{
 *   t: (key: string) => string,
 *   bot: Record<string, any>,
 *   form: Record<string, any>,
 *   runtimeInfo: Record<string, any> | null,
 *   webhookInfo: Record<string, any> | null,
 *   compactActions?: boolean,
 *   onSave: () => void,
 *   saving: boolean,
 *   hasChanges?: boolean,
 *   operationsLoading?: boolean,
 *   onRefreshRuntime?: () => void,
 *   onRegisterWebhook?: () => void,
 *   canRegisterWebhook?: boolean,
 *   canDeleteWebhook?: boolean,
 *   onDeleteWebhook?: () => void,
 *   onSyncCommands?: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotOverviewTab({
    t,
    bot,
    form,
    runtimeInfo,
    webhookInfo,
    compactActions = false,
    onSave,
    saving,
    hasChanges = true,
    operationsLoading = false,
    onRefreshRuntime = undefined,
    onRegisterWebhook = undefined,
    canRegisterWebhook = false,
    canDeleteWebhook = false,
    onDeleteWebhook = undefined,
    onSyncCommands = undefined,
}) {
    return (
        <div className="admin-telegram-bots__tab-layout">
            <TelegramBotBasicInfoCard
                t={t}
                bot={bot}
                form={form}
                saving={saving}
                hasChanges={hasChanges}
                showInlineSave={!compactActions}
                onSave={onSave}
            />

            <TelegramBotOverviewStats t={t} bot={bot} form={form} runtimeInfo={runtimeInfo} />

            <TelegramBotWebhookOperationsCard
                t={t}
                webhookInfo={webhookInfo}
                operationsLoading={operationsLoading}
                showActions={!compactActions}
                onRefreshRuntime={onRefreshRuntime}
                onRegisterWebhook={onRegisterWebhook}
                canRegisterWebhook={canRegisterWebhook}
                canDeleteWebhook={canDeleteWebhook}
                onDeleteWebhook={onDeleteWebhook}
                onSyncCommands={onSyncCommands}
            />
        </div>
    );
}

export default memo(TelegramBotOverviewTab);
