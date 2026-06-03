import { useCallback, useEffect, useMemo, useState } from 'react';
import { getCoinAlerts, toggleCoinAlert } from '../services/coin.service';

function countActiveAlerts(alerts) {
    return alerts.filter((alert) => alert.is_active).length;
}

/**
 * Own alert loading, derived metrics, and status updates.
 *
 * @param {{ loadErrorText: string, toggleErrorText: string }} options
 * @returns {{
 *   alerts: Array<Record<string, unknown>>,
 *   metrics: { total: number, active: number },
 *   loading: boolean,
 *   error: string,
 *   refreshAlerts: () => Promise<void>,
 *   toggleAlert: (id: number|string) => Promise<void>,
 * }}
 */
export function useCoinAlerts({ loadErrorText, toggleErrorText }) {
    const [alerts, setAlerts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const refreshAlerts = useCallback(async () => {
        setLoading(true);

        try {
            setAlerts(await getCoinAlerts());
            setError('');
        } catch {
            setAlerts([]);
            setError(loadErrorText);
        } finally {
            setLoading(false);
        }
    }, [loadErrorText]);

    useEffect(() => {
        void refreshAlerts();
    }, [refreshAlerts]);

    const toggleAlert = useCallback(
        async (id) => {
            try {
                await toggleCoinAlert(id);
                await refreshAlerts();
            } catch {
                setError(toggleErrorText);
            }
        },
        [refreshAlerts, toggleErrorText],
    );

    const metrics = useMemo(
        () => ({
            total: alerts.length,
            active: countActiveAlerts(alerts),
        }),
        [alerts],
    );

    return {
        alerts,
        metrics,
        loading,
        error,
        refreshAlerts,
        toggleAlert,
    };
}
