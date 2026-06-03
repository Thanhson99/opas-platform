import StockFavoriteButton from './StockFavoriteButton';

/**
 * Render one stock table row.
 *
 * @param {{
 *   stock: Record<string, unknown>,
 *   favoriteLabel: string,
 *   onFavoriteToggle: (symbol: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function StockTableRow({ stock, favoriteLabel, onFavoriteToggle }) {
    return (
        <tr>
            <td>{stock.symbol}</td>
            <td>{stock.name}</td>
            <td>{stock.exchange}</td>
            <td>
                <StockFavoriteButton
                    symbol={stock.symbol}
                    isFavorite={Boolean(stock.is_favorite)}
                    label={favoriteLabel}
                    onToggle={onFavoriteToggle}
                />
            </td>
        </tr>
    );
}
