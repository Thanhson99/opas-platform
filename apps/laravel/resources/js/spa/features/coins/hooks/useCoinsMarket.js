import { useCallback, useEffect, useMemo, useState } from 'react';
import { addFavoriteCoin, getCoins, removeFavoriteCoin } from '../services/coin.service';

/**
 * Own coin market loading, derived summaries, and favorite updates.
 *
 * @param {{ loadErrorText: string }} options
 * @returns {{
 *   coins: Array<Record<string, unknown>>,
 *   sortedCoins: Array<Record<string, unknown>>,
 *   summary: { count: number, favorites: number, positive: number, topMover: Record<string, unknown> | undefined },
 *   loading: boolean,
 *   error: string,
 *   refreshCoins: () => Promise<void>,
 *   toggleFavorite: (symbol: string) => Promise<void>,
 * }}
 */
export function useCoinsMarket({ loadErrorText }) {
    const [coins, setCoins] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const refreshCoins = useCallback(async () => {
        setLoading(true);

        try {
            setCoins(await getCoins());
            setError('');
        } catch {
            setError(loadErrorText);
        } finally {
            setLoading(false);
        }
    }, [loadErrorText]);

    useEffect(() => {
        void refreshCoins();
    }, [refreshCoins]);

    const sortedCoins = useMemo(
        () =>
            [...coins].sort((left, right) => {
                if (left.is_favorite === right.is_favorite) {
                    return Number(right.quoteVolume ?? 0) - Number(left.quoteVolume ?? 0);
                }

                return left.is_favorite ? -1 : 1;
            }),
        [coins],
    );

    const summary = useMemo(() => {
        const topMover = [...sortedCoins].sort(
            (left, right) =>
                Number(right.priceChangePercent ?? 0) - Number(left.priceChangePercent ?? 0),
        )[0];

        return {
            count: coins.length,
            favorites: sortedCoins.filter((coin) => coin.is_favorite).length,
            positive: sortedCoins.filter((coin) => Number(coin.priceChangePercent ?? 0) >= 0)
                .length,
            topMover,
        };
    }, [coins.length, sortedCoins]);

    const toggleFavorite = useCallback(
        async (symbol) => {
            const coin = coins.find((item) => item.symbol === symbol);

            if (!coin) {
                return;
            }

            if (coin.is_favorite) {
                await removeFavoriteCoin(symbol);
            } else {
                await addFavoriteCoin(symbol);
            }

            await refreshCoins();
        },
        [coins, refreshCoins],
    );

    return {
        coins,
        sortedCoins,
        summary,
        loading,
        error,
        refreshCoins,
        toggleFavorite,
    };
}
