import { memo } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';
import { buildMetadataRows, formatAuditTimestamp } from './telegramBotAdmin.helpers';

/**
 * Render the audit timeline tab for one Telegram bot.
 *
 * @param {{
 *   t: (key: string) => string,
 *   locale: string,
 *   loading: boolean,
 *   entries: Record<string, any>[],
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotAuditTab({ t, locale, loading, entries }) {
    if (loading) {
        return (
            <section className="admin-telegram-bots__card admin-telegram-bots__audit-shell">
                <header className="admin-telegram-bots__card-head">
                    <div>
                        <h4>{t('adminTelegramBots.auditTitle')}</h4>
                        <p>{t('adminTelegramBots.auditText')}</p>
                    </div>
                </header>
                <p className="admin-telegram-bots__helper">
                    <AppIcon name="refresh" />
                    {t('adminTelegramBots.auditLoading')}
                </p>
            </section>
        );
    }

    if (entries.length === 0) {
        return (
            <section className="admin-telegram-bots__card admin-telegram-bots__audit-shell">
                <header className="admin-telegram-bots__card-head">
                    <div>
                        <h4>{t('adminTelegramBots.auditTitle')}</h4>
                        <p>{t('adminTelegramBots.auditText')}</p>
                    </div>
                </header>
                <p className="admin-telegram-bots__helper">
                    <AppIcon name="info" />
                    {t('adminTelegramBots.auditEmpty')}
                </p>
            </section>
        );
    }

    return (
        <section className="admin-telegram-bots__card admin-telegram-bots__audit-shell">
            <header className="admin-telegram-bots__card-head">
                <div>
                    <h4>{t('adminTelegramBots.auditTitle')}</h4>
                    <p>{t('adminTelegramBots.auditText')}</p>
                </div>
            </header>
            <div className="admin-telegram-bots__audit-timeline">
                {entries.map((entry) => {
                    const rows = buildMetadataRows(entry.metadata);

                    return (
                        <article key={entry.id} className="admin-telegram-bots__audit-card">
                            <div className="admin-telegram-bots__audit-header">
                                <div>
                                    <strong>
                                        {t(`adminTelegramBots.auditActions.${entry.action}`)}
                                    </strong>
                                    <span>
                                        {entry.actor?.name ??
                                            entry.actor?.email ??
                                            t('adminTelegramBots.systemActor')}
                                    </span>
                                </div>
                                <time>{formatAuditTimestamp(entry.created_at, locale)}</time>
                            </div>
                            {rows.length > 0 ? (
                                <details className="admin-telegram-bots__details">
                                    <summary>
                                        <AppIcon name="terminal" />
                                        {t('adminTelegramBots.viewMetadata')}
                                    </summary>
                                    <dl className="admin-telegram-bots__metadata-list">
                                        {rows.map((row) => (
                                            <div
                                                key={`${entry.id}-${row.label}`}
                                                className="admin-telegram-bots__metadata-row"
                                            >
                                                <dt>{row.label}</dt>
                                                <dd>{row.value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </details>
                            ) : null}
                        </article>
                    );
                })}
            </div>
        </section>
    );
}

export default memo(TelegramBotAuditTab);
