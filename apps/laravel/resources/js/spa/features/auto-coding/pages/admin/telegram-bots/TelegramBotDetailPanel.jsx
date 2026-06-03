import { lazy, memo, Suspense, useEffect, useState } from 'react';
import { TelegramBotEmptyState } from './TelegramBotUi';
import TelegramBotOverviewTab from './TelegramBotOverviewTab';
import TelegramBotDetailHeader from './TelegramBotDetailHeader';
import TelegramBotTabs from './TelegramBotTabs';

const TelegramBotAccessControlTab = lazy(() => import('./TelegramBotAccessControlTab'));
const TelegramBotRuntimeTab = lazy(() => import('./TelegramBotRuntimeTab'));
const TelegramBotSecretsTab = lazy(() => import('./TelegramBotSecretsTab'));
const TelegramBotAuditTab = lazy(() => import('./TelegramBotAuditTab'));

function TelegramBotTabFallback({ t }) {
    return (
        <div className="admin-telegram-bots__empty">
            <strong>{t('adminTelegramBots.loading')}</strong>
        </div>
    );
}

/**
 * Render the selected bot detail workspace with tabs and actions.
 *
 * @param {{
 *   t: (key: string) => string,
 *   bot: Record<string, any> | null,
 *   form: Record<string, any> | null,
 *   compactHeader?: boolean,
 *   saving: boolean,
 *   hasChanges?: boolean,
 *   operationsLoading: boolean,
 *   runtimeInfo: Record<string, any> | null,
 *   webhookInfo: Record<string, any> | null,
 *   webhookForm: {url: string, drop_pending_updates: boolean},
 *   auditEntries: Record<string, any>[],
 *   auditLoading: boolean,
 *   revealedSecrets: Record<string, string>,
 *   onChange: (field: string, value: any) => void,
 *   onSave: () => void,
 *   onWebhookFormChange: (field: string, value: any) => void,
 *   onRefreshRuntime: () => void,
 *   onRegisterWebhook: () => void,
 *   onDeleteWebhook: () => void,
 *   onSyncCommands: () => void,
 *   onDeleteBot: () => void,
 *   onAuditTabOpen: () => void,
 *   onRevealSecret: (secretKey: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotDetailPanel({
    t,
    bot,
    form,
    compactHeader = false,
    saving,
    hasChanges = true,
    operationsLoading,
    runtimeInfo,
    webhookInfo,
    webhookForm,
    auditEntries,
    auditLoading,
    revealedSecrets,
    onChange,
    onSave,
    onWebhookFormChange,
    onRefreshRuntime,
    onRegisterWebhook,
    onDeleteWebhook,
    onSyncCommands,
    onDeleteBot,
    onAuditTabOpen,
    onRevealSecret,
}) {
    const [activeTab, setActiveTab] = useState('overview');
    const canRegisterWebhook = webhookForm.url.trim() !== '';
    const canDeleteWebhook = Boolean(webhookInfo?.result?.url);
    const overviewActionProps = compactHeader
        ? {}
        : {
              operationsLoading,
              onRefreshRuntime,
              onRegisterWebhook,
              canRegisterWebhook,
              canDeleteWebhook,
              onDeleteWebhook,
              onSyncCommands,
          };

    useEffect(() => {
        if (activeTab === 'audit') {
            onAuditTabOpen();
        }
    }, [activeTab, onAuditTabOpen]);

    if (!bot || !form) {
        return (
            <section className="admin-telegram-bots__panel admin-telegram-bots__panel--detail">
                <TelegramBotEmptyState
                    title={t('adminTelegramBots.emptyTitle')}
                    text={t('adminTelegramBots.emptyText')}
                />
            </section>
        );
    }

    return (
        <section className="admin-telegram-bots__panel admin-telegram-bots__panel--detail">
            <TelegramBotDetailHeader
                t={t}
                bot={bot}
                form={form}
                compact={compactHeader}
                saving={saving}
                hasChanges={hasChanges}
                onSave={onSave}
                onRefreshRuntime={onRefreshRuntime}
                onRegisterWebhook={onRegisterWebhook}
                canRegisterWebhook={canRegisterWebhook}
                canDeleteWebhook={canDeleteWebhook}
                onDeleteWebhook={onDeleteWebhook}
                onSyncCommands={onSyncCommands}
                onDeleteBot={onDeleteBot}
            />

            <TelegramBotTabs t={t} activeTab={activeTab} onTabChange={setActiveTab} />

            <div
                id={`telegram-bot-tab-panel-${activeTab}`}
                className="admin-telegram-bots__tab-panel"
                role="tabpanel"
                aria-labelledby={`telegram-bot-tab-${activeTab}`}
            >
                {activeTab === 'overview' ? (
                    <TelegramBotOverviewTab
                        t={t}
                        bot={bot}
                        form={form}
                        runtimeInfo={runtimeInfo}
                        webhookInfo={webhookInfo}
                        compactActions={compactHeader}
                        onSave={onSave}
                        saving={saving}
                        hasChanges={hasChanges}
                        {...overviewActionProps}
                    />
                ) : null}

                {activeTab === 'access' ? (
                    <Suspense fallback={<TelegramBotTabFallback t={t} />}>
                        <TelegramBotAccessControlTab t={t} form={form} onChange={onChange} />
                    </Suspense>
                ) : null}

                {activeTab === 'runtime' ? (
                    <Suspense fallback={<TelegramBotTabFallback t={t} />}>
                        <TelegramBotRuntimeTab
                            t={t}
                            runtimeInfo={runtimeInfo}
                            webhookInfo={webhookInfo}
                            webhookForm={webhookForm}
                            operationsLoading={operationsLoading}
                            onWebhookFormChange={onWebhookFormChange}
                            onRefreshRuntime={onRefreshRuntime}
                            onRegisterWebhook={onRegisterWebhook}
                            onDeleteWebhook={onDeleteWebhook}
                            onSyncCommands={onSyncCommands}
                        />
                    </Suspense>
                ) : null}

                {activeTab === 'secrets' ? (
                    <Suspense fallback={<TelegramBotTabFallback t={t} />}>
                        <TelegramBotSecretsTab
                            t={t}
                            bot={bot}
                            form={form}
                            revealedSecrets={revealedSecrets}
                            onChange={onChange}
                            onReveal={onRevealSecret}
                        />
                    </Suspense>
                ) : null}

                {activeTab === 'audit' ? (
                    <Suspense fallback={<TelegramBotTabFallback t={t} />}>
                        <TelegramBotAuditTab
                            t={t}
                            locale={form.locale}
                            loading={auditLoading}
                            entries={auditEntries}
                        />
                    </Suspense>
                ) : null}
            </div>
        </section>
    );
}

export default memo(TelegramBotDetailPanel);
