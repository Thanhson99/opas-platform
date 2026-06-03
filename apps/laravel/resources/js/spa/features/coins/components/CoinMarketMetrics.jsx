import MetricCard from '../../../components/ui/MetricCard';

/**
 * Render coin market summary metrics.
 *
 * @param {{
 *   summary: { count: number, favorites: number, positive: number },
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function CoinMarketMetrics({ summary, t }) {
    return (
        <section className="app-metrics-grid">
            <MetricCard
                label={t('coinsPage.metrics.tracked.label')}
                value={summary.count}
                hint={t('coinsPage.metrics.tracked.hint')}
                tone="sky"
            />
            <MetricCard
                label={t('coinsPage.metrics.favorites.label')}
                value={summary.favorites}
                hint={t('coinsPage.metrics.favorites.hint')}
                tone="amber"
            />
            <MetricCard
                label={t('coinsPage.metrics.positive.label')}
                value={summary.positive}
                hint={t('coinsPage.metrics.positive.hint')}
                tone="mint"
            />
        </section>
    );
}
