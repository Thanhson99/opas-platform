import { StockSearchInput, StockTableHead, StockTableRow } from './stock-market-table';

/**
 * Render the searchable stock table.
 *
 * @param {{
 *   query: string,
 *   stocks: Array<Record<string, unknown>>,
 *   t: (key: string) => string,
 *   onFavoriteToggle: (symbol: string) => void,
 *   onQueryChange: (query: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function StockMarketTable({ query, stocks, t, onFavoriteToggle, onQueryChange }) {
    const favoriteLabel = t('stocksPage.actions.toggleFavorite');
    const searchPlaceholder = t('stocksPage.searchPlaceholder');

    return (
        <section className="app-surface">
            <div className="app-surface__header">
                <div>
                    <h2 className="app-surface__title">{t('stocksPage.list.title')}</h2>
                    <p className="app-surface__text">{t('stocksPage.list.text')}</p>
                </div>
            </div>
            <StockSearchInput
                query={query}
                placeholder={searchPlaceholder}
                onQueryChange={onQueryChange}
            />
            <div className="app-table-wrap">
                <table className="app-table">
                    <StockTableHead t={t} />
                    <tbody>
                        {stocks.map((stock) => (
                            <StockTableRow
                                key={stock.symbol}
                                stock={stock}
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
