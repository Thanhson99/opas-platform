import { formatPercent } from '../../utils/coinFormatters';

/**
 * Render a signed percentage change pill.
 *
 * @param {{ value: unknown }} props
 * @returns {import('react').JSX.Element}
 */
export default function CoinMarketChangePill({ value }) {
    const isPositive = Number(value ?? 0) >= 0;

    return (
        <span className={`app-change-pill ${isPositive ? 'is-positive' : 'is-negative'}`}>
            {formatPercent(value)}
        </span>
    );
}
