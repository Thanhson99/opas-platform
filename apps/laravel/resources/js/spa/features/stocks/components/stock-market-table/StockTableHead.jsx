/**
 * Render translated stock table headers.
 *
 * @param {{ t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function StockTableHead({ t }) {
    return (
        <thead>
            <tr>
                <th>{t('stocksPage.table.symbol')}</th>
                <th>{t('stocksPage.table.company')}</th>
                <th>{t('stocksPage.table.exchange')}</th>
                <th>{t('stocksPage.table.favorite')}</th>
            </tr>
        </thead>
    );
}
