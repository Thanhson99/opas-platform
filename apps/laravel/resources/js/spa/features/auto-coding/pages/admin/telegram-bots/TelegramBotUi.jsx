import AppIcon from '../../../../../components/icons/AppIcon';

/**
 * Render one circular Telegram bot avatar.
 *
 * @param {{name?: string, tone?: string}} props
 * @returns {import('react').JSX.Element}
 */
export function TelegramBotAvatar({ name = 'mail', tone = 'primary' }) {
    return (
        <span
            className={`admin-telegram-bots__avatar admin-telegram-bots__avatar--${tone}`}
            aria-hidden="true"
        >
            <AppIcon name={name} />
        </span>
    );
}

/**
 * Render one compact badge used across the bot admin page.
 *
 * @param {{
 *   children: import('react').ReactNode,
 *   tone?: string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function TelegramBotBadge({ children, tone = 'neutral' }) {
    return (
        <span className={`admin-telegram-bots__badge admin-telegram-bots__badge--${tone}`}>
            {children}
        </span>
    );
}

/**
 * Render one summary metric card.
 *
 * @param {{
 *   label: string,
 *   value: string | number,
 *   tone?: string,
 *   hint?: string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function TelegramBotMetricCard({ label, value, tone = 'neutral', hint = '' }) {
    return (
        <article className={`admin-telegram-bots__metric admin-telegram-bots__metric--${tone}`}>
            <span className="admin-telegram-bots__metric-label">{label}</span>
            <strong className="admin-telegram-bots__metric-value">{value}</strong>
            {hint ? <span className="admin-telegram-bots__metric-hint">{hint}</span> : null}
        </article>
    );
}

/**
 * Render one label-value row for Telegram bot detail cards.
 *
 * @param {{
 *   label: string,
 *   children: import('react').ReactNode,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function TelegramBotDetailRow({ label, children }) {
    return (
        <div className="admin-telegram-bots__info-row">
            <span>{label}</span>
            <strong>{children}</strong>
        </div>
    );
}

/**
 * Render one empty state card inside the Telegram bot admin page.
 *
 * @param {{
 *   icon?: string,
 *   title: string,
 *   text: string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function TelegramBotEmptyState({ icon = 'mail', title, text }) {
    return (
        <div className="admin-telegram-bots__empty">
            <TelegramBotAvatar name={icon} tone="soft" />
            <strong>{title}</strong>
            <p>{text}</p>
        </div>
    );
}
