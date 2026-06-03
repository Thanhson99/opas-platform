import { useCallback, useEffect, useMemo, useState } from 'react';
import { getAdminAuthProviders, updateAdminAuthProvider } from '../services/auth.service';
import {
    buildInitialForm,
    buildProviderPayload,
    flattenMessages,
    getFieldIssues,
    isProviderFormDirty,
} from '../pages/admin/authProviderAdminForm.helpers';

/**
 * Own admin auth-provider form state, validation, loading, and save lifecycle.
 *
 * @param {{ providerKey: string, t: (key: string) => string }} options
 * @returns {Record<string, unknown>}
 */
export function useAuthProviderAdminForm({ providerKey, t }) {
    const [providers, setProviders] = useState([]);
    const [forms, setForms] = useState({});
    const [initialForms, setInitialForms] = useState({});
    const [touchedProviders, setTouchedProviders] = useState({});
    const [touchedFields, setTouchedFields] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [flash, setFlash] = useState(null);
    const [savingKey, setSavingKey] = useState('');
    const [serverErrors, setServerErrors] = useState({});
    const [secretEditState, setSecretEditState] = useState({});

    useEffect(() => {
        let mounted = true;

        const load = async () => {
            setLoading(true);

            try {
                const nextProviders = await getAdminAuthProviders();
                const nextForms = Object.fromEntries(
                    nextProviders.map((provider) => [provider.key, buildInitialForm(provider)]),
                );

                if (!mounted) {
                    return;
                }

                setProviders(nextProviders);
                setForms(nextForms);
                setInitialForms(nextForms);
                setTouchedProviders(
                    Object.fromEntries(nextProviders.map((provider) => [provider.key, false])),
                );
                setTouchedFields(
                    Object.fromEntries(nextProviders.map((provider) => [provider.key, {}])),
                );
                setSecretEditState({});
                setServerErrors({});
                setError('');
            } catch (requestError) {
                if (mounted) {
                    setProviders([]);
                    setError(requestError?.response?.data?.message || t('adminAuth.loadError'));
                }
            } finally {
                if (mounted) {
                    setLoading(false);
                }
            }
        };

        void load();

        return () => {
            mounted = false;
        };
    }, [t]);

    const selectedProvider = useMemo(
        () =>
            providers.find((provider) => provider.key === providerKey) ??
            providers.find((provider) => provider.key === 'email') ??
            providers[0] ??
            null,
        [providerKey, providers],
    );
    const form = selectedProvider ? forms[selectedProvider.key] : null;
    const initialForm = selectedProvider ? initialForms[selectedProvider.key] : null;
    const activeProviderCount = useMemo(
        () => providers.filter((provider) => provider.active).length,
        [providers],
    );
    const providerServerErrors = useMemo(
        () => (selectedProvider ? (serverErrors[selectedProvider.key] ?? {}) : {}),
        [selectedProvider, serverErrors],
    );
    const providerTouchedFields = useMemo(
        () => (selectedProvider ? (touchedFields[selectedProvider.key] ?? {}) : {}),
        [selectedProvider, touchedFields],
    );
    const hasTouchedProvider = selectedProvider
        ? Boolean(touchedProviders[selectedProvider.key])
        : false;
    const fieldIssues = useMemo(
        () => (selectedProvider && form ? getFieldIssues(selectedProvider, form, t) : {}),
        [form, selectedProvider, t],
    );
    const validationErrors = useMemo(
        () => [
            ...new Set(flattenMessages(fieldIssues).concat(flattenMessages(providerServerErrors))),
        ],
        [fieldIssues, providerServerErrors],
    );
    const hasUnsavedChanges = form && initialForm ? isProviderFormDirty(form, initialForm) : false;
    const isLastActiveProvider = selectedProvider?.active === true && activeProviderCount <= 1;
    const isEmailProvider = selectedProvider?.key === 'email';
    const canSave =
        Boolean(hasUnsavedChanges) &&
        validationErrors.length === 0 &&
        savingKey !== selectedProvider?.key;

    const clearProviderFieldError = useCallback(
        (fieldKey) => {
            if (!selectedProvider) {
                return;
            }

            setServerErrors((current) => {
                const nextProviderErrors = { ...(current[selectedProvider.key] ?? {}) };
                delete nextProviderErrors[fieldKey];

                return {
                    ...current,
                    [selectedProvider.key]: nextProviderErrors,
                };
            });
        },
        [selectedProvider],
    );

    const markFieldTouched = useCallback(
        (fieldKey) => {
            if (!selectedProvider) {
                return;
            }

            setTouchedFields((current) => ({
                ...current,
                [selectedProvider.key]: {
                    ...(current[selectedProvider.key] ?? {}),
                    [fieldKey]: true,
                },
            }));
        },
        [selectedProvider],
    );

    const updateForm = useCallback(
        (field, value) => {
            if (!selectedProvider) {
                return;
            }

            setForms((current) => ({
                ...current,
                [selectedProvider.key]: {
                    ...current[selectedProvider.key],
                    [field]: value,
                },
            }));
            setTouchedProviders((current) => ({
                ...current,
                [selectedProvider.key]: true,
            }));
            clearProviderFieldError(field);
            setFlash(null);
        },
        [clearProviderFieldError, selectedProvider],
    );

    const updateConfigField = useCallback(
        (bucket, field, value) => {
            if (!selectedProvider) {
                return;
            }

            setForms((current) => ({
                ...current,
                [selectedProvider.key]: {
                    ...current[selectedProvider.key],
                    [bucket]: {
                        ...current[selectedProvider.key][bucket],
                        [field]: value,
                    },
                },
            }));
            setTouchedProviders((current) => ({
                ...current,
                [selectedProvider.key]: true,
            }));
            clearProviderFieldError(`${bucket}.${field}`);
            setFlash(null);
        },
        [clearProviderFieldError, selectedProvider],
    );

    const updateSecretEditState = useCallback(
        (fieldKey, editing) => {
            if (!selectedProvider) {
                return;
            }

            setSecretEditState((current) => ({
                ...current,
                [`${selectedProvider.key}.${fieldKey}`]: editing,
            }));
        },
        [selectedProvider],
    );

    const performSaveProvider = useCallback(async () => {
        if (!selectedProvider || !form) {
            return;
        }

        setSavingKey(selectedProvider.key);
        setFlash({
            type: 'info',
            message: t('adminAuth.processingSave'),
        });
        setError('');
        setServerErrors((current) => ({
            ...current,
            [selectedProvider.key]: {},
        }));

        try {
            const nextProvider = await updateAdminAuthProvider(selectedProvider.key, {
                ...buildProviderPayload(form),
                enabled: isEmailProvider ? true : form.enabled,
            });
            const nextForm = buildInitialForm(nextProvider);

            setProviders((current) =>
                current.map((item) => (item.key === nextProvider.key ? nextProvider : item)),
            );
            setForms((current) => ({
                ...current,
                [selectedProvider.key]: nextForm,
            }));
            setInitialForms((current) => ({
                ...current,
                [selectedProvider.key]: nextForm,
            }));
            setTouchedProviders((current) => ({
                ...current,
                [selectedProvider.key]: false,
            }));
            setTouchedFields((current) => ({
                ...current,
                [selectedProvider.key]: {},
            }));
            setFlash({
                type: 'success',
                message: `${t('adminAuth.flash.savedPrefix')} ${nextProvider.display_name}.`,
            });
        } catch (requestError) {
            const nextErrors = requestError?.response?.data?.errors ?? {};

            setServerErrors((current) => ({
                ...current,
                [selectedProvider.key]: nextErrors,
            }));
            setFlash({
                type: 'error',
                message:
                    requestError?.response?.data?.message ||
                    `${t('adminAuth.saveErrorPrefix')} [${selectedProvider.key}].`,
            });
        } finally {
            setSavingKey('');
        }
    }, [form, isEmailProvider, selectedProvider, t]);

    return {
        providers,
        loading,
        error,
        flash,
        selectedProvider,
        form,
        fieldIssues,
        providerServerErrors,
        providerTouchedFields,
        hasTouchedProvider,
        validationErrors,
        hasUnsavedChanges,
        isLastActiveProvider,
        isEmailProvider,
        canSave,
        savingKey,
        secretEditState,
        setFlash,
        markFieldTouched,
        updateForm,
        updateConfigField,
        updateSecretEditState,
        performSaveProvider,
    };
}
