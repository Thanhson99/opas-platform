import { Link } from 'react-router-dom';
import PageHero from '../../../components/ui/PageHero';
import { formatPercent } from '../utils/coinFormatters';

/**
 * Render the coin market page hero and primary actions.
 *
 * @param {{ summary: Record<string, unknown>, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function CoinMarketHero({ summary, t }) {
    return (
        <PageHero
            eyebrow={t('coinsPage.hero.eyebrow')}
            title={t('coinsPage.hero.title')}
            text={t('coinsPage.hero.text')}
            actions={
                <>
                    <Link
                        to="/coins/price-alert-settings"
                        className="app-button app-button--primary"
                    >
                        {t('coinsPage.hero.alertsCta')}
                    </Link>
                    <Link to="/coins/feed-keywords" className="app-button app-button--ghost">
                        {t('coinsPage.hero.keywordsCta')}
                    </Link>
                </>
            }
            aside={summary.topMover ? <TopMoverCard coin={summary.topMover} t={t} /> : null}
        >
            <span className="app-chip">{t('coinsPage.hero.realtimeChip')}</span>
            <span className="app-chip">{t('coinsPage.hero.favoriteChip')}</span>
        </PageHero>
    );
}

/**
 * Render the highlighted top mover card.
 *
 * @param {{ coin: Record<string, unknown>, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
function TopMoverCard({ coin, t }) {
    return (
        <div className="app-hero-card app-hero-card--compact">
            <p className="app-hero-card__eyebrow">{t('coinsPage.hero.topMover')}</p>
            <h3 className="app-hero-card__title">{coin.symbol}</h3>
            <p className="app-hero-card__text">
                {t('coinsPage.hero.currentChange')} {formatPercent(coin.priceChangePercent)}
            </p>
        </div>
    );
}
