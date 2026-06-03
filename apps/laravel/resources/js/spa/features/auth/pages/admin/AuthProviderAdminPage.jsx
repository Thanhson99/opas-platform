import { lazy, Suspense, useCallback, useEffect, useMemo, useState } from 'react';
import { Navigate, useParams } from 'react-router-dom';
import ErrorState from '../../../../components/ui/ErrorState';
import LoadingState from '../../../../components/ui/LoadingState';
import AuthProviderAdminAlerts from '../../components/admin-auth-provider/AuthProviderAdminAlerts';
import AuthProviderAdminHeader from '../../components/admin-auth-provider/AuthProviderAdminHeader';
import AuthProviderAdminTabs from '../../components/admin-auth-provider/AuthProviderAdminTabs';
import AuthProviderBasicSection from '../../components/admin-auth-provider/AuthProviderBasicSection';
import AuthProviderFooterActions from '../../components/admin-auth-provider/AuthProviderFooterActions';
import AuthProviderHiddenAutofillFields from '../../components/admin-auth-provider/AuthProviderHiddenAutofillFields';
import { useAuth } from '../../context/AuthContext';
import { useAuthProviderAdminForm } from '../../hooks/useAuthProviderAdminForm';
import { useLanguage } from '../../../i18n/context/LanguageContext';
import { buildAuthProviderAdminViewModel } from './authProviderAdminView.helpers';

const AuthProviderDocsPanel = lazy(
    () => import('../../components/admin-auth-provider/AuthProviderDocsPanel'),
);
const AuthProviderConfirmModal = lazy(
    () => import('../../components/admin-auth-provider/AuthProviderConfirmModal'),
);
const AuthProviderPublicConfigSection = lazy(
    () => import('../../components/admin-auth-provider/AuthProviderPublicConfigSection'),
);
const AuthProviderSecretConfigSection = lazy(
    () => import('../../components/admin-auth-provider/AuthProviderSecretConfigSection'),
);

/**
 * Render the admin provider detail page that manages auth-provider config and readiness.
 */
export default function AuthProviderAdminPage() {
    const { key = 'email' } = useParams();
    const { user, loading: authLoading } = useAuth();
    const { t, language } = useLanguage();
    const [confirmState, setConfirmState] = useState(null);
    const [activeTab, setActiveTab] = useState('config');
    const [providerDocs, setProviderDocs] = useState(null);
    const [providerDocsKey, setProviderDocsKey] = useState(null);
    const {
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
    } = useAuthProviderAdminForm({ providerKey: key, t });

    useEffect(() => {
        setActiveTab('config');
        setProviderDocs(null);
        setProviderDocsKey(null);
    }, [key]);

    useEffect(() => {
        if (activeTab !== 'docs' || !selectedProvider) {
            return undefined;
        }

        const docsKey = `${selectedProvider.key}:${language}`;

        if (providerDocsKey === docsKey) {
            return undefined;
        }

        let isCurrent = true;

        setProviderDocs(null);

        import('./providerDocs').then(({ getProviderDocs }) => {
            if (!isCurrent) {
                return;
            }

            setProviderDocs(
                getProviderDocs(selectedProvider.key, language, {
                    callbackUrl: selectedProvider.metadata?.callback_url ?? null,
                }),
            );
            setProviderDocsKey(docsKey);
        });

        return () => {
            isCurrent = false;
        };
    }, [activeTab, language, providerDocsKey, selectedProvider]);

    const viewModel = useMemo(
        () =>
            selectedProvider && form
                ? buildAuthProviderAdminViewModel(selectedProvider, form, t)
                : null,
        [form, selectedProvider, t],
    );
    const handleFormSubmit = useCallback((event) => {
        event.preventDefault();
    }, []);
    const handleToggleRequest = useCallback((nextEnabled) => {
        setConfirmState({
            type: 'toggle',
            nextEnabled,
        });
    }, []);
    const handleSaveRequest = useCallback(() => {
        setConfirmState({ type: 'save' });
    }, []);
    const handleConfirmCancel = useCallback(() => {
        setConfirmState(null);
    }, []);
    const handleConfirmToggle = useCallback(
        (nextEnabled) => {
            updateForm('enabled', nextEnabled);
        },
        [updateForm],
    );
    const handleConfirmSave = useCallback(() => {
        void performSaveProvider();
    }, [performSaveProvider]);
    const handleLastProviderBlocked = useCallback(() => {
        setFlash({
            type: 'error',
            message: t('adminAuth.lastProviderText'),
        });
    }, [setFlash, t]);

    if (!authLoading && (!user || user.role !== 'admin')) {
        return <Navigate to="/login" replace />;
    }

    if (loading) {
        return <LoadingState text={t('adminAuth.loading')} />;
    }

    if (providers.length === 0) {
        return <ErrorState text={t('adminAuth.loadError')} />;
    }

    if (!selectedProvider) {
        return <ErrorState text={t('adminAuth.loadError')} />;
    }

    if (selectedProvider.key !== key) {
        return <Navigate to={`/admin/auth/providers/${selectedProvider.key}`} replace />;
    }

    if (!form || !viewModel) {
        return <LoadingState text={t('adminAuth.loading')} />;
    }

    const { statusTone, visibilityLabel, summary } = viewModel;
    const hasPublicConfig = Object.keys(form.public_config).length > 0;
    const hasSecretConfig = Object.keys(form.secret_config).length > 0;

    return (
        <div className="app-shell">
            <section className="app-provider-page">
                <AuthProviderAdminHeader
                    provider={selectedProvider}
                    form={form}
                    statusTone={statusTone}
                    visibilityLabel={visibilityLabel}
                    summary={summary}
                    t={t}
                />

                <AuthProviderAdminAlerts
                    error={error}
                    flash={flash}
                    provider={selectedProvider}
                    providerServerErrors={providerServerErrors}
                    isLastActiveProvider={isLastActiveProvider}
                    t={t}
                />

                <AuthProviderAdminTabs activeTab={activeTab} onChange={setActiveTab} t={t} />

                {activeTab === 'docs' ? (
                    providerDocs ? (
                        <Suspense fallback={<LoadingState text={t('adminAuth.loading')} />}>
                            <AuthProviderDocsPanel docs={providerDocs} t={t} />
                        </Suspense>
                    ) : (
                        <LoadingState text={t('adminAuth.loading')} />
                    )
                ) : (
                    <form className="app-form" autoComplete="off" onSubmit={handleFormSubmit}>
                        <AuthProviderHiddenAutofillFields />
                        <AuthProviderBasicSection
                            provider={selectedProvider}
                            form={form}
                            fieldIssues={fieldIssues}
                            serverErrors={providerServerErrors}
                            touchedFields={providerTouchedFields}
                            onFieldBlur={markFieldTouched}
                            onFieldChange={updateForm}
                            t={t}
                        />

                        {hasPublicConfig ? (
                            <Suspense fallback={null}>
                                <AuthProviderPublicConfigSection
                                    provider={selectedProvider}
                                    form={form}
                                    fieldIssues={fieldIssues}
                                    serverErrors={providerServerErrors}
                                    touchedFields={providerTouchedFields}
                                    onFieldBlur={markFieldTouched}
                                    onConfigChange={updateConfigField}
                                    t={t}
                                />
                            </Suspense>
                        ) : null}

                        {hasSecretConfig ? (
                            <Suspense fallback={null}>
                                <AuthProviderSecretConfigSection
                                    provider={selectedProvider}
                                    form={form}
                                    fieldIssues={fieldIssues}
                                    serverErrors={providerServerErrors}
                                    touchedFields={providerTouchedFields}
                                    secretEditState={secretEditState}
                                    onFieldBlur={markFieldTouched}
                                    onConfigChange={updateConfigField}
                                    onSecretEditChange={updateSecretEditState}
                                    t={t}
                                />
                            </Suspense>
                        ) : null}

                        <AuthProviderFooterActions
                            form={form}
                            hasUnsavedChanges={hasUnsavedChanges}
                            hasTouchedProvider={hasTouchedProvider}
                            validationErrors={validationErrors}
                            isEmailProvider={isEmailProvider}
                            canSave={canSave}
                            saving={savingKey === selectedProvider.key}
                            onToggleRequest={handleToggleRequest}
                            onSaveRequest={handleSaveRequest}
                            t={t}
                        />
                    </form>
                )}
            </section>

            {confirmState ? (
                <Suspense fallback={null}>
                    <AuthProviderConfirmModal
                        confirmState={confirmState}
                        provider={selectedProvider}
                        isLastActiveProvider={isLastActiveProvider}
                        onCancel={handleConfirmCancel}
                        onConfirmToggle={handleConfirmToggle}
                        onConfirmSave={handleConfirmSave}
                        onLastProviderBlocked={handleLastProviderBlocked}
                        t={t}
                    />
                </Suspense>
            ) : null}
        </div>
    );
}
