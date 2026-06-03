import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import { useLanguage } from '../../i18n/context/LanguageContext';
import VideoAutomationHero from '../components/VideoAutomationHero';
import VideoAutomationMetrics from '../components/VideoAutomationMetrics';
import VideoGroupGrid from '../components/VideoGroupGrid';
import { useTrendingVideos } from '../hooks/useTrendingVideos';

/**
 * Render grouped trending-video sources for the automation workflow.
 */
export default function VideosPage() {
    const { t } = useLanguage();
    const { groups, totalVideos, loading, error } = useTrendingVideos({
        loadErrorText: t('videosPage.loadError'),
    });

    if (loading) return <LoadingState text={t('videosPage.loading')} />;
    if (error) return <ErrorState text={error} />;

    return (
        <div className="app-shell">
            <VideoAutomationHero t={t} />
            <VideoAutomationMetrics groupCount={groups.length} totalVideos={totalVideos} t={t} />
            <VideoGroupGrid groups={groups} t={t} />
        </div>
    );
}
