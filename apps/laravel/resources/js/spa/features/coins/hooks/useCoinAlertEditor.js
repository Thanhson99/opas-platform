import { useCallback, useEffect, useState } from 'react';
import { getCoinAlert, updateCoinAlert } from '../services/coin.service';

function buildAlertPayload(form) {
    return {
        threshold_percent: form.threshold_percent,
        type: form.type,
        direction: form.direction || null,
        is_active: Boolean(form.is_active),
    };
}

/**
 * Own alert editor loading, form state, and save behavior.
 *
 * @param {{ id: number|string|undefined, enabled: boolean, loadErrorText: string, saveErrorText: string }} options
 * @returns {{
 *   form: Record<string, unknown>|null,
 *   loading: boolean,
 *   error: string,
 *   setForm: import('react').Dispatch<import('react').SetStateAction<Record<string, unknown>|null>>,
 *   saveAlert: () => Promise<boolean>,
 * }}
 */
export function useCoinAlertEditor({ id, enabled, loadErrorText, saveErrorText }) {
    const [form, setForm] = useState(null);
    const [loading, setLoading] = useState(Boolean(enabled));
    const [error, setError] = useState('');

    useEffect(() => {
        if (!enabled || !id) {
            setLoading(false);
            return undefined;
        }

        let mounted = true;

        const loadAlert = async () => {
            setLoading(true);

            try {
                const alert = await getCoinAlert(id);

                if (mounted) {
                    setForm(alert);
                    setError('');
                }
            } catch {
                if (mounted) {
                    setForm(null);
                    setError(loadErrorText);
                }
            } finally {
                if (mounted) {
                    setLoading(false);
                }
            }
        };

        void loadAlert();

        return () => {
            mounted = false;
        };
    }, [enabled, id, loadErrorText]);

    const saveAlert = useCallback(async () => {
        if (!id || !form) {
            return false;
        }

        try {
            await updateCoinAlert(id, buildAlertPayload(form));
            setError('');

            return true;
        } catch {
            setError(saveErrorText);

            return false;
        }
    }, [form, id, saveErrorText]);

    return {
        form,
        loading,
        error,
        setForm,
        saveAlert,
    };
}
