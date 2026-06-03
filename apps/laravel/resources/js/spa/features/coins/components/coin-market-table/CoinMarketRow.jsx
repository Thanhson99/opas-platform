import { Link } from 'react-router-dom';
import { formatCompactUsd, formatUsd } from '../../utils/coinFormatters';
import CoinFavoriteButton from './CoinFavoriteButton';
import CoinMarketChangePill from './CoinMarketChangePill';

/**
 * Render one coin market table row.
 *
 * @param {{
 *   coin: Record<string, unknown>,
 *   favoriteLabel: string,
 *   onFavoriteToggle: (symbol: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function CoinMarketRow({ coin, favoriteLabel, onFavoriteToggle }) {
    return (
        <tr>
            <td>
                <Link className="app-link" to={`/coins/show/${coin.symbol}`}>
                    {coin.symbol}
                </Link>
            </td>
            <td className="app-table__value-strong">{formatUsd(coin.lastPrice)}</td>
            <td className="app-table__value-soft">{formatCompactUsd(coin.quoteVolume)}</td>
            <td>
                <CoinMarketChangePill value={coin.priceChangePercent} />
            </td>
            <td className="app-table__align-center">
                <CoinFavoriteButton
                    symbol={coin.symbol}
                    isFavorite={Boolean(coin.is_favorite)}
                    label={favoriteLabel}
                    onToggle={onFavoriteToggle}
                />
            </td>
        </tr>
    );
}
