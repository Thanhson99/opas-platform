/**
 * Render translated coin market table headers.
 *
 * @param {{ t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function CoinMarketTableHead({ t }) {
    return (
        <thead>
            <tr>
                <th>{t('coinsPage.table.symbol')}</th>
                <th>{t('coinsPage.table.price')}</th>
                <th>{t('coinsPage.table.volume')}</th>
                <th>{t('coinsPage.table.change')}</th>
                <th className="app-table__align-center">{t('coinsPage.table.favorite')}</th>
            </tr>
        </thead>
    );
}
