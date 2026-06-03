/**
 * Render key-value market fields for one coin.
 *
 * @param {{ rows: Array<Array<string|number|undefined|null>> }} props
 * @returns {import('react').JSX.Element}
 */
export default function CoinDetailGrid({ rows }) {
    return (
        <section className="app-detail-grid">
            {rows.map(([label, value]) => (
                <article className="app-detail-card" key={label}>
                    <p className="app-detail-card__label">{label}</p>
                    <p className="app-detail-card__value">{value}</p>
                </article>
            ))}
        </section>
    );
}
