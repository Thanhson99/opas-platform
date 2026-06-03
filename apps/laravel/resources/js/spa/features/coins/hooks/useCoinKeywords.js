import { useCallback, useEffect, useMemo, useState } from 'react';
import { createCoinKeyword, deleteCoinKeyword, getCoinKeywords } from '../services/coin.service';

const emptyForm = { keyword: '', tags: '' };

function parseTags(tags) {
    return tags
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean);
}

function countTaggedKeywords(keywords) {
    return keywords.filter((item) => (item.tags ?? []).length > 0).length;
}

/**
 * Own keyword loading, form state, derived metrics, and keyword mutations.
 *
 * @param {{ loadErrorText: string, createErrorText: string, deleteErrorText: string }} options
 * @returns {{
 *   keywords: Array<Record<string, unknown>>,
 *   form: { keyword: string, tags: string },
 *   metrics: { total: number, tagged: number },
 *   loading: boolean,
 *   error: string,
 *   setForm: import('react').Dispatch<import('react').SetStateAction<{ keyword: string, tags: string }>>,
 *   refreshKeywords: () => Promise<void>,
 *   createKeyword: () => Promise<void>,
 *   deleteKeyword: (id: number|string) => Promise<void>,
 * }}
 */
export function useCoinKeywords({ loadErrorText, createErrorText, deleteErrorText }) {
    const [keywords, setKeywords] = useState([]);
    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState(emptyForm);
    const [error, setError] = useState('');

    const refreshKeywords = useCallback(async () => {
        setLoading(true);

        try {
            setKeywords(await getCoinKeywords());
            setError('');
        } catch {
            setKeywords([]);
            setError(loadErrorText);
        } finally {
            setLoading(false);
        }
    }, [loadErrorText]);

    useEffect(() => {
        void refreshKeywords();
    }, [refreshKeywords]);

    const createKeyword = useCallback(async () => {
        try {
            await createCoinKeyword({ keyword: form.keyword, tags: parseTags(form.tags) });
            setForm(emptyForm);
            await refreshKeywords();
        } catch {
            setError(createErrorText);
        }
    }, [createErrorText, form.keyword, form.tags, refreshKeywords]);

    const deleteKeyword = useCallback(
        async (id) => {
            try {
                await deleteCoinKeyword(id);
                await refreshKeywords();
            } catch {
                setError(deleteErrorText);
            }
        },
        [deleteErrorText, refreshKeywords],
    );

    const metrics = useMemo(
        () => ({
            total: keywords.length,
            tagged: countTaggedKeywords(keywords),
        }),
        [keywords],
    );

    return {
        keywords,
        form,
        metrics,
        loading,
        error,
        setForm,
        refreshKeywords,
        createKeyword,
        deleteKeyword,
    };
}
