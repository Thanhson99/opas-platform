import PageHero from '../../../components/ui/PageHero';

/**
 * Render the stock monitor page hero.
 *
 * @param {{ t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function StockMarketHero({ t }) {
    return (
        <PageHero
            eyebrow={t('stocksPage.hero.eyebrow')}
            title={t('stocksPage.hero.title')}
            text={t('stocksPage.hero.text')}
        >
            <span className="app-chip">{t('stocksPage.hero.searchChip')}</span>
            <span className="app-chip">{t('stocksPage.hero.favoriteChip')}</span>
        </PageHero>
    );
}
