import { useEffect, useMemo, useState } from 'react';
import { getCoin } from '../services/coin.service';

function buildCoinDetailRows(coin, t) {
    return [
        [t('coinDetailPage.fields.symbol'), coin.symbol],
        [t('coinDetailPage.fields.lastPrice'), coin.lastPrice],
        [t('coinDetailPage.fields.high'), coin.highPrice],
        [t('coinDetailPage.fields.low'), coin.lowPrice],
        [t('coinDetailPage.fields.open'), coin.openPrice],
        [t('coinDetailPage.fields.change'), `${coin.priceChangePercent}%`],
    ];
}

/**
 * Own coin detail loading and derived detail rows.
 *
 * @param {{ symbol: string|undefined, loadErrorText: string, t: (key: string) => string }} options
 * @returns {{
 *   coin: Record<string, unknown>|null,
 *   detailRows: Array<Array<string|number|undefined|null>>,
 *   loading: boolean,
 *   error: string,
 * }}
 */
export function useCoinDetail({ symbol, loadErrorText, t }) {
    const [coin, setCoin] = useState(null);
    const [loading, setLoading] = useState(Boolean(symbol));
    const [error, setError] = useState('');

    useEffect(() => {
        if (!symbol) {
            setLoading(false);
            setCoin(null);
            return undefined;
        }

        let mounted = true;

        const loadCoin = async () => {
            setLoading(true);

            try {
                const nextCoin = await getCoin(symbol);

                if (mounted) {
                    setCoin(nextCoin);
                    setError('');
                }
            } catch {
                if (mounted) {
                    setCoin(null);
                    setError(loadErrorText);
                }
            } finally {
                if (mounted) {
                    setLoading(false);
                }
            }
        };

        void loadCoin();

        return () => {
            mounted = false;
        };
    }, [loadErrorText, symbol]);

    const detailRows = useMemo(() => (coin ? buildCoinDetailRows(coin, t) : []), [coin, t]);

    return {
        coin,
        detailRows,
        loading,
        error,
    };
}
