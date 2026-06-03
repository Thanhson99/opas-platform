import MetricCard from '../../../components/ui/MetricCard';

/**
 * Render price-alert summary metrics.
 *
 * @param {{
 *   metrics: { total: number, active: number },
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function AlertMetrics({ metrics, t }) {
    return (
        <section className="app-metrics-grid">
            <MetricCard
                label={t('alertsPage.metrics.total.label')}
                value={metrics.total}
                hint={t('alertsPage.metrics.total.hint')}
                tone="sky"
            />
            <MetricCard
                label={t('alertsPage.metrics.active.label')}
                value={metrics.active}
                hint={t('alertsPage.metrics.active.hint')}
                tone="mint"
            />
        </section>
    );
}
