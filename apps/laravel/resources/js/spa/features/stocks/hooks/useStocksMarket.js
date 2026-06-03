import { useCallback, useEffect, useMemo, useState } from 'react';
import { addFavoriteStock, getStocks, removeFavoriteStock } from '../services/stock.service';

/**
 * Own stock loading, filtering, derived metrics, and favorite updates.
 *
 * @param {{ loadErrorText: string }} options
 * @returns {{
 *   query: string,
 *   setQuery: (query: string) => void,
 *   filteredStocks: Array<Record<string, unknown>>,
 *   metrics: { exchanges: number, favorites: number },
 *   loading: boolean,
 *   error: string,
 *   toggleFavorite: (symbol: string) => Promise<void>,
 * }}
 */
export function useStocksMarket({ loadErrorText }) {
    const [stocks, setStocks] = useState([]);
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const refreshStocks = useCallback(async () => {
        setLoading(true);

        try {
            setStocks(await getStocks());
            setError('');
        } catch {
            setError(loadErrorText);
        } finally {
            setLoading(false);
        }
    }, [loadErrorText]);

    useEffect(() => {
        void refreshStocks();
    }, [refreshStocks]);

    const filteredStocks = useMemo(() => {
        const needle = query.toLowerCase().trim();
        const source = !needle
            ? stocks
            : stocks.filter((stock) =>
                  [stock.symbol, stock.name, stock.exchange]
                      .join(' ')
                      .toLowerCase()
                      .includes(needle),
              );

        return [...source].sort((left, right) => {
            if (left.is_favorite === right.is_favorite) {
                return String(left.symbol).localeCompare(String(right.symbol));
            }

            return left.is_favorite ? -1 : 1;
        });
    }, [query, stocks]);

    const metrics = useMemo(
        () => ({
            exchanges: new Set(filteredStocks.map((stock) => stock.exchange).filter(Boolean)).size,
            favorites: filteredStocks.filter((stock) => stock.is_favorite).length,
        }),
        [filteredStocks],
    );

    const toggleFavorite = useCallback(
        async (symbol) => {
            const stock = stocks.find((item) => item.symbol === symbol);

            if (!stock) {
                return;
            }

            if (stock.is_favorite) {
                await removeFavoriteStock(symbol);
            } else {
                await addFavoriteStock(symbol);
            }

            await refreshStocks();
        },
        [refreshStocks, stocks],
    );

    return {
        query,
        setQuery,
        filteredStocks,
        metrics,
        loading,
        error,
        toggleFavorite,
    };
}
