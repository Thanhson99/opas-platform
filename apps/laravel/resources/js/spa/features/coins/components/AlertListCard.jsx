import { Link } from 'react-router-dom';
import AppIcon from '../../../components/icons/AppIcon';
import EmptyState from '../../../components/ui/EmptyState';

/**
 * Render the price-alert management list.
 *
 * @param {{
 *   alerts: Array<Record<string, unknown>>,
 *   t: (key: string) => string,
 *   getEditLink: (alert: Record<string, unknown>) => { to: string, state?: Record<string, unknown> },
 *   onToggle: (id: number|string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function AlertListCard({ alerts, t, getEditLink, onToggle }) {
    return (
        <section className="app-surface">
            <div className="app-surface__header">
                <div>
                    <h2 className="app-surface__title">{t('alertsPage.list.title')}</h2>
                    <p className="app-surface__text">{t('alertsPage.list.text')}</p>
                </div>
            </div>
            {alerts.length === 0 ? <EmptyState text={t('alertsPage.list.empty')} /> : null}
            <div className="app-card-stack">
                {alerts.map((alert) => (
                    <AlertListItem
                        alert={alert}
                        editLink={getEditLink(alert)}
                        key={alert.id}
                        t={t}
                        onToggle={() => onToggle(alert.id)}
                    />
                ))}
            </div>
        </section>
    );
}

/**
 * Render one price-alert list row.
 *
 * @param {{
 *   alert: Record<string, unknown>,
 *   editLink: { to: string, state?: Record<string, unknown> },
 *   t: (key: string) => string,
 *   onToggle: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AlertListItem({ alert, editLink, t, onToggle }) {
    return (
        <article className="app-list-card">
            <div className="app-list-card__head">
                <div>
                    <strong className="app-list-card__title">
                        {alert.threshold_percent == null
                            ? t('alertsPage.customThreshold')
                            : `${alert.threshold_percent}%`}
                    </strong>
                    <div className="app-chip-row">
                        <span className="app-chip">{alert.type}</span>
                        <span className="app-chip">{alert.direction ?? t('common.none')}</span>
                        <AlertStatusPill isActive={Boolean(alert.is_active)} t={t} />
                    </div>
                </div>
                <div className="app-action-row">
                    <Link
                        className="app-button app-button--primary"
                        to={editLink.to}
                        state={editLink.state}
                        title={`${t('common.edit')} ${alert.threshold_percent ?? t('alertsPage.customThreshold')}`}
                        aria-label={`${t('common.edit')} ${alert.threshold_percent ?? t('alertsPage.customThreshold')}`}
                    >
                        <AppIcon name="edit" />
                        {t('common.edit')}
                    </Link>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={onToggle}
                        title={t('alertsPage.actions.toggle')}
                    >
                        <AppIcon name={alert.is_active ? 'x' : 'check'} />
                        {t('alertsPage.actions.toggle')}
                    </button>
                </div>
            </div>
        </article>
    );
}

/**
 * Render the alert active/inactive status pill.
 *
 * @param {{ isActive: boolean, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
function AlertStatusPill({ isActive, t }) {
    return (
        <span
            className={`app-status-pill ${
                isActive ? 'app-status-pill--success' : 'app-status-pill--muted'
            }`}
        >
            {isActive ? t('alertsPage.status.active') : t('alertsPage.status.inactive')}
        </span>
    );
}
