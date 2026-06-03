import { useEffect, useState } from 'react';
import { getAdminAuthProviders } from '../services/auth.service';

/**
 * Own admin auth-provider loading state.
 *
 * @param {{ loadErrorText: string }} options
 * @returns {{ providers: Array<Record<string, unknown>>, loading: boolean, error: string }}
 */
export function useAdminAuthProviders({ loadErrorText }) {
    const [providers, setProviders] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        let mounted = true;

        const loadProviders = async () => {
            setLoading(true);

            try {
                const nextProviders = await getAdminAuthProviders();

                if (mounted) {
                    setProviders(nextProviders);
                    setError('');
                }
            } catch (requestError) {
                if (mounted) {
                    setProviders([]);
                    setError(requestError?.response?.data?.message || loadErrorText);
                }
            } finally {
                if (mounted) {
                    setLoading(false);
                }
            }
        };

        void loadProviders();

        return () => {
            mounted = false;
        };
    }, [loadErrorText]);

    return {
        providers,
        loading,
        error,
    };
}
