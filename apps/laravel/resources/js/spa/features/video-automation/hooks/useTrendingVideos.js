import { useEffect, useMemo, useState } from 'react';
import { getTrendingVideos } from '../services/video.service';

/**
 * Own trending-video loading and derived counts.
 *
 * @param {{ loadErrorText: string }} options
 * @returns {{ groups: Array<Record<string, unknown>>, totalVideos: number, loading: boolean, error: string }}
 */
export function useTrendingVideos({ loadErrorText }) {
    const [groups, setGroups] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        const loadTrendingVideos = async () => {
            setLoading(true);

            try {
                setGroups(await getTrendingVideos());
                setError('');
            } catch {
                setGroups([]);
                setError(loadErrorText);
            } finally {
                setLoading(false);
            }
        };

        void loadTrendingVideos();
    }, [loadErrorText]);

    const totalVideos = useMemo(
        () => groups.reduce((count, group) => count + group.links.length, 0),
        [groups],
    );

    return {
        groups,
        totalVideos,
        loading,
        error,
    };
}
