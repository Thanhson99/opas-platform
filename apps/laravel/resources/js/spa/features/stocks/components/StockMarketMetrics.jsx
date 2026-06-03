import MetricCard from '../../../components/ui/MetricCard';

/**
 * Render stock monitor summary metrics.
 *
 * @param {{
 *   filteredCount: number,
 *   metrics: { exchanges: number, favorites: number },
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function StockMarketMetrics({ filteredCount, metrics, t }) {
    return (
        <section className="app-metrics-grid">
            <MetricCard
                label={t('stocksPage.metrics.filtered.label')}
                value={filteredCount}
                hint={t('stocksPage.metrics.filtered.hint')}
                tone="sky"
            />
            <MetricCard
                label={t('stocksPage.metrics.exchanges.label')}
                value={metrics.exchanges}
                hint={t('stocksPage.metrics.exchanges.hint')}
                tone="violet"
            />
            <MetricCard
                label={t('stocksPage.metrics.favorites.label')}
                value={metrics.favorites}
                hint={t('stocksPage.metrics.favorites.hint')}
                tone="amber"
            />
        </section>
    );
}
