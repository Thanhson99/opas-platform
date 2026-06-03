import PageHero from '../../../components/ui/PageHero';

/**
 * Render the video automation page hero.
 *
 * @param {{ t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function VideoAutomationHero({ t }) {
    return (
        <PageHero
            eyebrow={t('videosPage.hero.eyebrow')}
            title={t('videosPage.hero.title')}
            text={t('videosPage.hero.text')}
        >
            <span className="app-chip">{t('videosPage.hero.groupChip')}</span>
            <span className="app-chip">{t('videosPage.hero.openChip')}</span>
        </PageHero>
    );
}
