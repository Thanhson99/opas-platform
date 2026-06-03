import MetricCard from '../../../components/ui/MetricCard';

/**
 * Render grouped video source metrics.
 *
 * @param {{ groupCount: number, totalVideos: number, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function VideoAutomationMetrics({ groupCount, totalVideos, t }) {
    return (
        <section className="app-metrics-grid">
            <MetricCard
                label={t('videosPage.metrics.groups.label')}
                value={groupCount}
                hint={t('videosPage.metrics.groups.hint')}
                tone="sky"
            />
            <MetricCard
                label={t('videosPage.metrics.sources.label')}
                value={totalVideos}
                hint={t('videosPage.metrics.sources.hint')}
                tone="amber"
            />
        </section>
    );
}
