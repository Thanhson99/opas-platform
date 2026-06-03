import { memo, useCallback, useEffect } from 'react';
import { createPortal } from 'react-dom';
import AppIcon from '../../../../../components/icons/AppIcon';
import TelegramBotDetailPanel from './TelegramBotDetailPanel';
import { TelegramBotBadge } from './TelegramBotUi';
import { resolveBotEnvironmentTone } from './telegramBotAdmin.helpers';

function TelegramBotEditSummary({ t, bot, form, runtimeInfo, webhookInfo }) {
    const isRuntimeActive = runtimeInfo?.key === bot?.key;
    const chatCount = form?.allowed_chat_ids?.length ?? 0;
    const userCount = form?.allowed_user_ids?.length ?? 0;
    const actionCount = form?.allowed_actions?.length ?? 0;
    const secretCount = [
        Boolean(bot?.secret_status?.bot_token),
        Boolean(bot?.secret_status?.webhook_secret),
    ].filter(Boolean).length;
    const readinessItems = [
        {
            key: 'secrets',
            isReady: secretCount === 2,
            tab: t('adminTelegramBots.tabs.secrets'),
        },
        {
            key: 'access',
            isReady: chatCount > 0 || userCount > 0,
            tab: t('adminTelegramBots.tabs.access'),
        },
        {
            key: 'actions',
            isReady: actionCount > 0,
            tab: t('adminTelegramBots.tabs.access'),
        },
        {
            key: 'webhook',
            isReady: webhookInfo?.ok === true && Boolean(webhookInfo?.result?.url),
            tab: t('adminTelegramBots.tabs.runtime'),
        },
    ];
    const readyCount = readinessItems.filter((item) => item.isReady).length;
    const missingItems = readinessItems.filter((item) => !item.isReady);
    const nextMissingItem = missingItems[0] ?? null;
    const readinessPercent = Math.round((readyCount / readinessItems.length) * 100);
    const readinessTone =
        readyCount === readinessItems.length ? 'ready' : readyCount >= 2 ? 'partial' : 'needs-work';
    const summaryItems = [
        {
            icon: 'activity',
            label: t('adminTelegramBots.modalSummary.runtime'),
            value: isRuntimeActive
                ? t('adminTelegramBots.runtime.editingActiveBot')
                : t('adminTelegramBots.runtime.editingInactiveBot'),
            isReady: isRuntimeActive,
        },
        {
            icon: 'shield',
            label: t('adminTelegramBots.modalSummary.access'),
            value: `${chatCount} ${t('adminTelegramBots.modalSummary.chats')}, ${userCount} ${t(
                'adminTelegramBots.modalSummary.users',
            )}`,
            isReady: chatCount > 0 || userCount > 0,
        },
        {
            icon: 'lock',
            label: t('adminTelegramBots.modalSummary.secrets'),
            value: `${secretCount}/2 ${t('adminTelegramBots.modalSummary.ready')}`,
            isReady: secretCount === 2,
        },
        {
            icon: 'terminal',
            label: t('adminTelegramBots.modalSummary.actions'),
            value: `${actionCount} ${t('adminTelegramBots.modalSummary.allowed')}`,
            isReady: actionCount > 0,
        },
    ];

    return (
        <div
            className="admin-telegram-bots__edit-summary"
            aria-label={t('adminTelegramBots.modalSummary.title')}
        >
            {summaryItems.map((item) => (
                <article
                    className={`admin-telegram-bots__edit-summary-item ${
                        item.isReady ? 'is-ready' : 'is-warning'
                    }`}
                    key={item.label}
                >
                    <span>
                        <AppIcon name={item.isReady ? 'check' : item.icon} />
                    </span>
                    <div>
                        <small>{item.label}</small>
                        <strong>{item.value}</strong>
                        <em>
                            {item.isReady
                                ? t('adminTelegramBots.modalSummary.readyState')
                                : t('adminTelegramBots.modalSummary.needsSetupState')}
                        </em>
                    </div>
                </article>
            ))}
            <article className={`admin-telegram-bots__edit-readiness is-${readinessTone}`}>
                <div className="admin-telegram-bots__edit-readiness-head">
                    <div role="status">
                        <small>{t('adminTelegramBots.modalSummary.readiness')}</small>
                        <strong>
                            {readyCount}/{readinessItems.length}{' '}
                            {t('adminTelegramBots.modalSummary.configured')}
                        </strong>
                        <p>{t(`adminTelegramBots.modalSummary.readinessText.${readinessTone}`)}</p>
                    </div>
                    <span>{readinessPercent}%</span>
                </div>
                <div
                    className="admin-telegram-bots__edit-readiness-track"
                    role="progressbar"
                    aria-label={t('adminTelegramBots.modalSummary.readiness')}
                    aria-valuemin={0}
                    aria-valuemax={readinessItems.length}
                    aria-valuenow={readyCount}
                >
                    <span style={{ width: `${readinessPercent}%` }} />
                </div>
                {missingItems.length > 0 ? (
                    <div className="admin-telegram-bots__edit-readiness-missing">
                        <AppIcon name="info" />
                        <span>
                            {t('adminTelegramBots.modalSummary.missing')}:{' '}
                            {missingItems
                                .map((item) =>
                                    t(`adminTelegramBots.modalSummary.checks.${item.key}`),
                                )
                                .join(', ')}
                        </span>
                    </div>
                ) : null}
                {nextMissingItem ? (
                    <div className="admin-telegram-bots__edit-next-action">
                        <AppIcon name="arrow-right" />
                        <span>
                            {t('adminTelegramBots.modalSummary.nextActionPrefix')}{' '}
                            <strong>{nextMissingItem.tab}</strong>
                        </span>
                    </div>
                ) : null}
            </article>
        </div>
    );
}

/**
 * Render the full Telegram bot editor inside a modal.
 *
 * @param {{
 *   open: boolean,
 *   t: (key: string) => string,
 *   bot: Record<string, any> | null,
 *   form: Record<string, any> | null,
 *   saving: boolean,
 *   hasChanges: boolean,
 *   operationsLoading: boolean,
 *   runtimeInfo: Record<string, any> | null,
 *   webhookInfo: Record<string, any> | null,
 *   webhookForm: {url: string, drop_pending_updates: boolean},
 *   auditEntries: Record<string, any>[],
 *   auditLoading: boolean,
 *   revealedSecrets: Record<string, string>,
 *   onClose: () => void,
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
 * @returns {import('react').ReactPortal | null}
 */
function TelegramBotEditModal({
    open,
    t,
    bot,
    form,
    saving,
    hasChanges,
    operationsLoading,
    runtimeInfo,
    webhookInfo,
    webhookForm,
    auditEntries,
    auditLoading,
    revealedSecrets,
    onClose,
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
    const requestClose = useCallback(() => {
        if (saving) {
            return;
        }

        if (
            hasChanges &&
            typeof window !== 'undefined' &&
            !window.confirm(t('adminTelegramBots.unsavedChangesConfirm'))
        ) {
            return;
        }

        onClose();
    }, [hasChanges, onClose, saving, t]);

    useEffect(() => {
        if (!open || typeof document === 'undefined') {
            return undefined;
        }

        const { body, documentElement } = document;
        const previousBodyOverflow = body.style.overflow;
        const previousHtmlOverflow = documentElement.style.overflow;
        const handleKeyDown = (event) => {
            if (event.key === 'Escape' && !saving) {
                requestClose();
            }
        };

        body.style.overflow = 'hidden';
        documentElement.style.overflow = 'hidden';
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            body.style.overflow = previousBodyOverflow;
            documentElement.style.overflow = previousHtmlOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [open, requestClose, saving]);

    const handleBackdropClick = useCallback(() => {
        requestClose();
    }, [requestClose]);
    const handleDialogClick = useCallback((event) => {
        event.stopPropagation();
    }, []);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    const environment = bot?.environment ?? form?.environment ?? 'local';
    const purpose = bot?.purpose ?? form?.purpose ?? 'remote_control';
    const isRuntimeActive = runtimeInfo?.key === bot?.key;
    const runtimeStateTone = !bot?.enabled
        ? 'danger'
        : isRuntimeActive
          ? 'success'
          : bot?.is_default
            ? 'warning'
            : 'neutral';
    const runtimeStateKey = !bot?.enabled
        ? 'disabled'
        : isRuntimeActive
          ? 'live'
          : bot?.is_default
            ? 'defaultPending'
            : 'standby';

    return createPortal(
        <div
            className="admin-telegram-bots__drawer-backdrop admin-telegram-bots__modal-backdrop"
            role="presentation"
            onClick={handleBackdropClick}
        >
            <section
                className="admin-telegram-bots__edit-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="telegram-bot-edit-modal-title"
                aria-busy={saving}
                onClick={handleDialogClick}
            >
                <div className="admin-telegram-bots__edit-modal-bar">
                    <div className="admin-telegram-bots__edit-modal-title">
                        <p className="admin-telegram-bots__section-eyebrow">
                            {t('adminTelegramBots.editEyebrow')}
                        </p>
                        <h3 id="telegram-bot-edit-modal-title">
                            {bot?.display_name ?? t('adminTelegramBots.editTitle')}
                        </h3>
                        <div className="admin-telegram-bots__edit-modal-meta">
                            <code>{form?.bot_username || `@${bot?.key ?? ''}`}</code>
                            <span>{bot?.key}</span>
                        </div>
                        <p>{t('adminTelegramBots.editText')}</p>
                        <div
                            className={`admin-telegram-bots__edit-runtime-state is-${runtimeStateTone}`}
                            role="status"
                        >
                            <AppIcon
                                name={
                                    runtimeStateTone === 'success'
                                        ? 'check'
                                        : runtimeStateTone === 'danger'
                                          ? 'lock'
                                          : 'activity'
                                }
                            />
                            <span>{t(`adminTelegramBots.runtimeState.${runtimeStateKey}`)}</span>
                        </div>
                    </div>
                    <div
                        className="admin-telegram-bots__edit-modal-status"
                        aria-label={t('adminTelegramBots.modalStatusLabel')}
                    >
                        <TelegramBotBadge tone={resolveBotEnvironmentTone(environment)}>
                            {t(`adminTelegramBots.classification.environment.${environment}`)}
                        </TelegramBotBadge>
                        <TelegramBotBadge tone="purpose">
                            {t(`adminTelegramBots.classification.purpose.${purpose}`)}
                        </TelegramBotBadge>
                        {bot?.is_default ? (
                            <TelegramBotBadge tone="soft-success">
                                <span className="admin-telegram-bots__badge-dot" />
                                {t('adminTelegramBots.status.defaultShort')}
                            </TelegramBotBadge>
                        ) : null}
                        <TelegramBotBadge tone={bot?.enabled ? 'success' : 'danger'}>
                            {bot?.enabled
                                ? t('adminTelegramBots.status.enabled')
                                : t('adminTelegramBots.status.disabled')}
                        </TelegramBotBadge>
                    </div>
                    <button
                        type="button"
                        className="admin-telegram-bots__icon-button"
                        disabled={saving}
                        onClick={requestClose}
                        title={t('common.cancel')}
                        aria-label={t('common.cancel')}
                    >
                        <AppIcon name="x" />
                    </button>
                </div>

                <TelegramBotEditSummary
                    t={t}
                    bot={bot}
                    form={form}
                    runtimeInfo={runtimeInfo}
                    webhookInfo={webhookInfo}
                />

                <TelegramBotDetailPanel
                    t={t}
                    bot={bot}
                    form={form}
                    compactHeader
                    saving={saving}
                    hasChanges={hasChanges}
                    operationsLoading={operationsLoading}
                    runtimeInfo={runtimeInfo}
                    webhookInfo={webhookInfo}
                    webhookForm={webhookForm}
                    auditEntries={auditEntries}
                    auditLoading={auditLoading}
                    revealedSecrets={revealedSecrets}
                    onChange={onChange}
                    onSave={onSave}
                    onWebhookFormChange={onWebhookFormChange}
                    onRefreshRuntime={onRefreshRuntime}
                    onRegisterWebhook={onRegisterWebhook}
                    onDeleteWebhook={onDeleteWebhook}
                    onSyncCommands={onSyncCommands}
                    onDeleteBot={onDeleteBot}
                    onAuditTabOpen={onAuditTabOpen}
                    onRevealSecret={onRevealSecret}
                />
            </section>
        </div>,
        document.body,
    );
}

export default memo(TelegramBotEditModal);
