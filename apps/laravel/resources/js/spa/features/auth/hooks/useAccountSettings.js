import { useEffect, useMemo, useState } from 'react';
import { unlinkAccountProvider, updateAccountProfile } from '../services/auth.service';

function firstErrorMessage(requestError, fallbackMessage) {
    const errors = requestError?.response?.data?.errors;

    if (errors && typeof errors === 'object') {
        const firstField = Object.values(errors)[0];

        if (Array.isArray(firstField) && firstField[0]) {
            return firstField[0];
        }
    }

    return requestError?.response?.data?.message || fallbackMessage;
}

/**
 * Own account settings state, profile save, and provider unlink actions.
 *
 * @param {{
 *   refreshAuthProviders: () => Promise<void>,
 *   refreshUser: () => Promise<void>,
 *   t: (key: string) => string,
 *   user: Record<string, unknown>,
 * }} options
 * @returns {{
 *   name: string,
 *   formError: string,
 *   providerError: string,
 *   flash: string,
 *   saving: boolean,
 *   unlinkingProviderKey: string,
 *   confirmProvider: Record<string, unknown>|null,
 *   linkedProviders: Array<Record<string, unknown>>,
 *   currentSignInProvider: Record<string, unknown>|undefined,
 *   nameChanged: boolean,
 *   setName: (name: string) => void,
 *   setConfirmProvider: import('react').Dispatch<import('react').SetStateAction<Record<string, unknown>|null>>,
 *   saveProfile: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 *   unlinkProvider: () => Promise<void>,
 * }}
 */
export function useAccountSettings({ refreshAuthProviders, refreshUser, t, user }) {
    const [name, setName] = useState('');
    const [formError, setFormError] = useState('');
    const [providerError, setProviderError] = useState('');
    const [flash, setFlash] = useState('');
    const [saving, setSaving] = useState(false);
    const [unlinkingProviderKey, setUnlinkingProviderKey] = useState('');
    const [confirmProvider, setConfirmProvider] = useState(null);

    useEffect(() => {
        setName(user?.name ?? '');
    }, [user?.name]);

    const linkedProviders = useMemo(
        () => (Array.isArray(user?.linked_providers) ? user.linked_providers : []),
        [user?.linked_providers],
    );
    const currentSignInProvider = user.current_sign_in_provider;
    const nameChanged = name.trim() !== (user.name ?? '').trim();

    const saveProfile = async (event) => {
        event.preventDefault();

        if (!name.trim() || !nameChanged) {
            return;
        }

        setSaving(true);
        setFormError('');
        setFlash('');

        try {
            const response = await updateAccountProfile({ name: name.trim() });

            setFlash(response.message || t('accountSettings.profileSaved'));
            await refreshUser();
        } catch (requestError) {
            setFormError(firstErrorMessage(requestError, t('accountSettings.saveError')));
        } finally {
            setSaving(false);
        }
    };

    const unlinkProvider = async () => {
        if (!confirmProvider) {
            return;
        }

        setUnlinkingProviderKey(confirmProvider.key);
        setProviderError('');
        setFlash('');

        try {
            const response = await unlinkAccountProvider(confirmProvider.key);

            setFlash(response.message || t('accountSettings.unlinkSuccess'));
            setConfirmProvider(null);
            await Promise.all([refreshUser(), refreshAuthProviders()]);
        } catch (requestError) {
            setProviderError(firstErrorMessage(requestError, t('accountSettings.unlinkError')));
        } finally {
            setUnlinkingProviderKey('');
        }
    };

    return {
        name,
        formError,
        providerError,
        flash,
        saving,
        unlinkingProviderKey,
        confirmProvider,
        linkedProviders,
        currentSignInProvider,
        nameChanged,
        setName,
        setConfirmProvider,
        saveProfile,
        unlinkProvider,
    };
}
