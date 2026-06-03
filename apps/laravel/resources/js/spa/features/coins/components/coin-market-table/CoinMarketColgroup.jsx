/**
 * Render fixed column sizing for the coin market table.
 *
 * @returns {import('react').JSX.Element}
 */
export default function CoinMarketColgroup() {
    return (
        <colgroup>
            <col className="app-table__col-symbol" />
            <col className="app-table__col-price" />
            <col className="app-table__col-volume" />
            <col className="app-table__col-change" />
            <col className="app-table__col-favorite" />
        </colgroup>
    );
}
