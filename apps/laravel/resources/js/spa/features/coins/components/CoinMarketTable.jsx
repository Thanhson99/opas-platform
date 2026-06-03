import { CoinMarketColgroup, CoinMarketRow, CoinMarketTableHead } from './coin-market-table';

/**
 * Render the coin market table with favorite controls.
 *
 * @param {{
 *   coins: Array<Record<string, unknown>>,
 *   t: (key: string) => string,
 *   onFavoriteToggle: (symbol: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function CoinMarketTable({ coins, t, onFavoriteToggle }) {
    const favoriteLabel = t('coinsPage.actions.toggleFavorite');

    return (
        <section className="app-surface">
            <div className="app-surface__header">
                <div>
                    <h2 className="app-surface__title">{t('coinsPage.list.title')}</h2>
                    <p className="app-surface__text">{t('coinsPage.list.text')}</p>
                </div>
            </div>
            <div className="app-inline-stats">
                <span className="app-inline-badge">{t('coinsPage.list.symbolHint')}</span>
                <span className="app-inline-badge">{t('coinsPage.list.favoriteHint')}</span>
            </div>
            <div className="app-table-wrap app-table-wrap--wide">
                <table className="app-table app-table--coins">
                    <CoinMarketColgroup />
                    <CoinMarketTableHead t={t} />
                    <tbody>
                        {coins.map((coin) => (
                            <CoinMarketRow
                                coin={coin}
                                key={coin.symbol}
                                favoriteLabel={favoriteLabel}
                                onFavoriteToggle={onFavoriteToggle}
                            />
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
