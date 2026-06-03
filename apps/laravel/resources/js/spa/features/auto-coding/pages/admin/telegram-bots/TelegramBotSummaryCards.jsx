import { memo, useMemo } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';

/**
 * Render high-level Telegram bot counters.
 *
 * @param {{
 *   bots: Record<string, any>[],
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotSummaryCards({ bots, t }) {
    const cards = useMemo(() => {
        const enabledCount = bots.filter((bot) => bot.enabled).length;
        const defaultBot = bots.find((bot) => bot.is_default);
        const secretReadyCount = bots.filter(
            (bot) => bot.secret_status?.bot_token && bot.secret_status?.webhook_secret,
        ).length;
        const hasBots = bots.length > 0;
        const allSecretsReady = hasBots && secretReadyCount === bots.length;

        return [
            {
                icon: 'bot',
                tone: 'blue',
                label: t('adminTelegramBots.metrics.totalBots'),
                value: bots.length,
                hint: t('adminTelegramBots.metrics.totalBotsHint'),
                state: hasBots
                    ? t('adminTelegramBots.metrics.stateOk')
                    : t('adminTelegramBots.metrics.stateNeedsSetup'),
                stateTone: hasBots ? 'ok' : 'warning',
            },
            {
                icon: 'check',
                tone: 'green',
                label: t('adminTelegramBots.metrics.enabledBots'),
                value: enabledCount,
                hint:
                    enabledCount > 0
                        ? t('adminTelegramBots.metrics.enabledBotsHint')
                        : t('adminTelegramBots.metrics.enabledBotsMissing'),
                state:
                    enabledCount > 0
                        ? t('adminTelegramBots.metrics.stateOk')
                        : t('adminTelegramBots.metrics.stateNeedsSetup'),
                stateTone: enabledCount > 0 ? 'ok' : 'warning',
            },
            {
                icon: 'shield',
                tone: 'amber',
                label: t('adminTelegramBots.metrics.secretReady'),
                value: secretReadyCount,
                hint: allSecretsReady
                    ? t('adminTelegramBots.metrics.secretReadyHint')
                    : t('adminTelegramBots.metrics.secretReadyMissing'),
                state: allSecretsReady
                    ? t('adminTelegramBots.metrics.stateOk')
                    : t('adminTelegramBots.metrics.stateNeedsSetup'),
                stateTone: allSecretsReady ? 'ok' : 'warning',
            },
            {
                icon: 'target',
                tone: 'purple',
                label: t('adminTelegramBots.metrics.defaultBot'),
                value: defaultBot
                    ? t('adminTelegramBots.metrics.defaultBotReady')
                    : t('adminTelegramBots.metrics.defaultBotMissing'),
                hint: defaultBot?.display_name ?? t('adminTelegramBots.metrics.noDefaultBot'),
                state: defaultBot
                    ? t('adminTelegramBots.metrics.stateOk')
                    : t('adminTelegramBots.metrics.stateNeedsSetup'),
                stateTone: defaultBot ? 'ok' : 'warning',
            },
        ];
    }, [bots, t]);

    return (
        <section className="admin-telegram-bots__summary-grid">
            {cards.map((card) => (
                <article
                    key={card.label}
                    className={`admin-telegram-bots__summary-card admin-telegram-bots__summary-card--${card.tone}`}
                >
                    <span className="admin-telegram-bots__summary-icon" aria-hidden="true">
                        <AppIcon name={card.icon} />
                    </span>
                    <div>
                        <span>{card.label}</span>
                        <strong>{card.value}</strong>
                        <small>{card.hint}</small>
                        <em className={`is-${card.stateTone}`}>{card.state}</em>
                    </div>
                </article>
            ))}
        </section>
    );
}

export default memo(TelegramBotSummaryCards);
