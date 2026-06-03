import MetricCard from '../../../components/ui/MetricCard';

/**
 * Render keyword summary metrics for the content automation page.
 *
 * @param {{
 *   metrics: { total: number, tagged: number },
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function KeywordMetrics({ metrics, t }) {
    return (
        <section className="app-metrics-grid">
            <MetricCard
                label={t('keywordsPage.metrics.total.label')}
                value={metrics.total}
                hint={t('keywordsPage.metrics.total.hint')}
                tone="sky"
            />
            <MetricCard
                label={t('keywordsPage.metrics.tagged.label')}
                value={metrics.tagged}
                hint={t('keywordsPage.metrics.tagged.hint')}
                tone="mint"
            />
        </section>
    );
}
