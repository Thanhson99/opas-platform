import { useEffect, useState } from 'react';
import { Navigate, useParams } from 'react-router-dom';
import AppIcon, { hasAppIcon } from '../../../../components/icons/AppIcon';
import ConfirmModal from '../../../../components/ui/ConfirmModal';
import ErrorState from '../../../../components/ui/ErrorState';
import LoadingState from '../../../../components/ui/LoadingState';
import api from '../../../../lib/api';
import AuthProviderField from '../../components/AuthProviderField';
import SensitiveInput from '../../components/SensitiveInput';
import { useAuth } from '../../context/AuthContext';
import { useLanguage } from '../../../i18n/context/LanguageContext';
import {
    buildBaseMeta,
    buildFieldMeta,
    buildInitialForm,
    buildProviderPayload,
    flattenMessages,
    getAvailableIconNames,
    getFieldIssues,
    getProviderSummary,
    isProviderFormDirty,
} from './authProviderAdminForm.helpers';
import { getProviderDocs } from './providerDocs';

/**
 * Prefer server-side validation messages and only reveal client-side issues after touch.
 */
function getFieldError(fieldIssues, serverErrors, fieldTouches, key) {
    if (serverErrors[key]?.[0]) {
        return serverErrors[key][0];
    }

    if (!fieldTouches[key]) {
        return '';
    }

    return fieldIssues[key] || '';
}

/**
 * Build stable input names for provider settings fields and secret inputs.
 */
function buildProviderInputName(providerKey, fieldKey, bucket = 'base') {
    return `auth-provider-${providerKey}-${bucket}-${fieldKey}`;
}

/**
 * Render the lightweight inline markdown tokens used by provider setup docs.
 */
function renderInlineRichText(text) {
    const tokens = String(text)
        .split(/(`[^`]+`|\*\*[^*]+\*\*)/g)
        .filter(Boolean);

    return tokens.map((token, index) => {
        if (token.startsWith('**') && token.endsWith('**')) {
            return <strong key={`${token}-${index}`}>{token.slice(2, -2)}</strong>;
        }

        if (token.startsWith('`') && token.endsWith('`')) {
            return <code key={`${token}-${index}`}>{token.slice(1, -1)}</code>;
        }

        return token;
    });
}

/**
 * Render the admin provider detail page that manages auth-provider config and readiness.
 */
export default function AuthProviderAdminPage() {
    const { key = 'email' } = useParams();
    const { user, loading: authLoading } = useAuth();
    const { t, language } = useLanguage();
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
    const [confirmState, setConfirmState] = useState(null);
    const [activeTab, setActiveTab] = useState('config');
    const [secretEditState, setSecretEditState] = useState({});

    useEffect(() => {
        const load = async () => {
            setLoading(true);

            try {
                const response = await api.get('/admin/auth/providers');
                const nextProviders = response.data.data ?? [];

                setProviders(nextProviders);
                const nextForms = Object.fromEntries(
                    nextProviders.map((provider) => [provider.key, buildInitialForm(provider)]),
                );

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
                setProviders([]);
                setError(requestError?.response?.data?.message || t('adminAuth.loadError'));
            } finally {
                setLoading(false);
            }
        };

        void load();
    }, [t]);

    useEffect(() => {
        setActiveTab('config');
    }, [key]);

    if (!authLoading && (!user || user.role !== 'admin')) {
        return <Navigate to="/login" replace />;
    }

    if (loading) {
        return <LoadingState text={t('adminAuth.loading')} />;
    }

    if (providers.length === 0) {
        return <ErrorState text={t('adminAuth.loadError')} />;
    }

    const activeProviderCount = providers.filter((provider) => provider.active).length;
    const selectedProvider =
        providers.find((provider) => provider.key === key) ??
        providers.find((provider) => provider.key === 'email') ??
        providers[0];

    if (!selectedProvider) {
        return <ErrorState text={t('adminAuth.loadError')} />;
    }

    if (selectedProvider.key !== key) {
        return <Navigate to={`/admin/auth/providers/${selectedProvider.key}`} replace />;
    }

    const form = forms[selectedProvider.key];
    const initialForm = initialForms[selectedProvider.key];

    if (!form || !initialForm) {
        return <LoadingState text={t('adminAuth.loading')} />;
    }

    const providerServerErrors = serverErrors[selectedProvider.key] ?? {};
    const hasTouchedProvider = Boolean(touchedProviders[selectedProvider.key]);
    const providerTouchedFields = touchedFields[selectedProvider.key] ?? {};
    const fieldIssues = getFieldIssues(selectedProvider, form, t);
    const validationErrors = [
        ...new Set(flattenMessages(fieldIssues).concat(flattenMessages(providerServerErrors))),
    ];
    const hasUnsavedChanges = isProviderFormDirty(form, initialForm);
    const canSave =
        hasUnsavedChanges && validationErrors.length === 0 && savingKey !== selectedProvider.key;
    const isLastActiveProvider = selectedProvider.active && activeProviderCount <= 1;
    const isEmailProvider = selectedProvider.key === 'email';
    const statusTone = selectedProvider.active
        ? 'app-status-pill--success'
        : selectedProvider.ready
          ? 'app-status-pill--muted'
          : 'app-status-pill--warning';
    const visibilityLabel =
        form.visibility === 'public'
            ? t('adminAuth.visibility.public')
            : form.visibility === 'hidden'
              ? t('adminAuth.visibility.hidden')
              : t('adminAuth.visibility.adminOnly');
    const providerDocs = getProviderDocs(selectedProvider.key, language, {
        callbackUrl: selectedProvider.metadata?.callback_url ?? null,
    });

    /**
     * Clear one backend validation error after the admin edits the related field again.
     */
    const clearProviderFieldError = (fieldKey) => {
        setServerErrors((current) => {
            const nextProviderErrors = { ...(current[selectedProvider.key] ?? {}) };
            delete nextProviderErrors[fieldKey];

            return {
                ...current,
                [selectedProvider.key]: nextProviderErrors,
            };
        });
    };

    /**
     * Record user interaction so client-side validation errors appear intentionally.
     */
    const markFieldTouched = (fieldKey) => {
        setTouchedFields((current) => ({
            ...current,
            [selectedProvider.key]: {
                ...(current[selectedProvider.key] ?? {}),
                [fieldKey]: true,
            },
        }));
    };

    /**
     * Update one top-level provider field and mark the current provider form dirty.
     */
    const updateForm = (field, value) => {
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
    };

    /**
     * Update one nested public or secret config field for the selected provider.
     */
    const updateConfigField = (bucket, field, value) => {
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
    };

    /**
     * Track whether one masked secret field is currently in edit mode.
     */
    const updateSecretEditState = (fieldKey, editing) => {
        setSecretEditState((current) => ({
            ...current,
            [`${selectedProvider.key}.${fieldKey}`]: editing,
        }));
    };

    /**
     * Persist the selected provider and replace local form state with the saved response.
     */
    const performSaveProvider = async () => {
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
            const response = await api.put(`/admin/auth/providers/${selectedProvider.key}`, {
                ...buildProviderPayload(form),
                enabled: isEmailProvider ? true : form.enabled,
            });
            const nextProvider = response.data.data;

            setProviders((current) =>
                current.map((item) => (item.key === nextProvider.key ? nextProvider : item)),
            );
            const nextForm = buildInitialForm(nextProvider);

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
    };

    return (
        <div className="app-shell">
            <section className="app-provider-page">
                <div className="app-provider-page__head">
                    <p className="app-provider-card__eyebrow">{selectedProvider.key}</p>
                    <div className="app-provider-page__title-row">
                        {hasAppIcon(form.icon) ? (
                            <span className="app-provider-page__icon">
                                <AppIcon name={form.icon} />
                            </span>
                        ) : null}
                        <h1 className="app-provider-page__title">
                            {form.display_name || selectedProvider.display_name}
                        </h1>
                    </div>
                    <p className="app-provider-page__text">
                        {getProviderSummary(selectedProvider, t)}
                    </p>
                </div>

                {error ? <ErrorState text={error} /> : null}
                {flash ? (
                    <div
                        className={`app-provider-note ${
                            flash.type === 'success'
                                ? 'app-provider-note--success'
                                : flash.type === 'info'
                                  ? 'app-provider-note--info'
                                  : 'app-provider-note--error'
                        }`}
                    >
                        {flash.message}
                    </div>
                ) : null}

                {selectedProvider.issues?.length ? (
                    <div className="app-provider-note">
                        <strong>{t('adminAuth.currentStatus')}</strong>{' '}
                        {selectedProvider.issues.join(' ')}
                    </div>
                ) : null}

                {providerServerErrors.enabled?.[0] ? (
                    <div className="app-provider-note app-provider-note--error">
                        {providerServerErrors.enabled[0]}
                    </div>
                ) : null}

                <div className="app-chip-row">
                    <span className={`app-status-pill ${statusTone}`}>
                        {selectedProvider.active
                            ? t('adminAuth.status.live')
                            : selectedProvider.ready
                              ? t('adminAuth.status.ready')
                              : t('adminAuth.status.incomplete')}
                    </span>
                    <span className="app-chip">{selectedProvider.type}</span>
                    <span className="app-chip">
                        {t('adminAuth.visibility.chip')} {visibilityLabel}
                    </span>
                </div>

                <div className="app-provider-inline-tabs" role="tablist" aria-label="Provider tabs">
                    <button
                        type="button"
                        className={`app-provider-inline-tab ${
                            activeTab === 'config' ? 'is-active' : ''
                        }`}
                        onClick={() => setActiveTab('config')}
                    >
                        {language === 'vi' ? 'Thiết lập' : 'Setup'}
                    </button>
                    <button
                        type="button"
                        className={`app-provider-inline-tab ${
                            activeTab === 'docs' ? 'is-active' : ''
                        }`}
                        onClick={() => setActiveTab('docs')}
                    >
                        {language === 'vi' ? 'Hướng dẫn' : 'Guide'}
                    </button>
                </div>

                {isLastActiveProvider ? (
                    <div className="app-provider-note">
                        <strong>{t('adminAuth.lastProviderTitle')}</strong>{' '}
                        {t('adminAuth.lastProviderText')}
                    </div>
                ) : null}

                {activeTab === 'docs' ? (
                    <section className="app-provider-docs">
                        <div className="app-provider-docs__head">
                            <h3 className="app-form-card__title">{providerDocs.title}</h3>
                            <p className="app-form-card__text">{providerDocs.intro}</p>
                        </div>

                        {providerDocs.links.length > 0 ? (
                            <div className="app-provider-docs__links">
                                {providerDocs.links.map((link) => (
                                    <a
                                        key={link.url}
                                        href={link.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="app-provider-docs__link"
                                    >
                                        <span>{link.label}</span>
                                        <span className="app-provider-docs__link-arrow">
                                            {language === 'vi' ? 'Mở' : 'Open'}
                                        </span>
                                    </a>
                                ))}
                            </div>
                        ) : null}

                        <div className="app-provider-docs__content">
                            {providerDocs.steps.map((step) => (
                                <section key={step.title} className="app-provider-docs__section">
                                    <h4 className="app-provider-docs__section-title">
                                        {step.title}
                                    </h4>
                                    <ol className="app-provider-docs__list">
                                        {step.items.map((item) => (
                                            <li key={item}>{renderInlineRichText(item)}</li>
                                        ))}
                                    </ol>
                                </section>
                            ))}

                            {providerDocs.fields.length > 0 ? (
                                <section className="app-provider-docs__section">
                                    <h4 className="app-provider-docs__section-title">
                                        {language === 'vi'
                                            ? 'Cách điền vào form cấu hình'
                                            : 'How to fill this configuration form'}
                                    </h4>
                                    <ul className="app-provider-docs__list app-provider-docs__list--plain">
                                        {providerDocs.fields.map((field) => (
                                            <li key={field.label}>
                                                <strong>{field.label}:</strong>{' '}
                                                {renderInlineRichText(field.text)}
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ) : null}
                        </div>
                    </section>
                ) : (
                    <form
                        className="app-form"
                        autoComplete="off"
                        onSubmit={(event) => event.preventDefault()}
                    >
                        <input
                            type="text"
                            name="fake-admin-username"
                            autoComplete="username"
                            tabIndex={-1}
                            aria-hidden="true"
                            className="app-visually-hidden"
                        />
                        <input
                            type="password"
                            name="fake-admin-password"
                            autoComplete="current-password"
                            tabIndex={-1}
                            aria-hidden="true"
                            className="app-visually-hidden"
                        />
                        <section className="app-provider-section">
                            <div className="app-provider-section__head">
                                <h3 className="app-form-card__title">
                                    {t('adminAuth.sections.basic.title')}
                                </h3>
                                <p className="app-form-card__text">
                                    {t('adminAuth.sections.basic.text')}
                                </p>
                            </div>

                            <div className="app-provider-grid">
                                {['display_name', 'icon', 'sort_order'].map((field) => {
                                    const meta = buildBaseMeta(t, field);
                                    const errorMessage = getFieldError(
                                        fieldIssues,
                                        providerServerErrors,
                                        providerTouchedFields,
                                        field,
                                    );
                                    const isIconField = field === 'icon';
                                    const iconName = form.icon.trim();
                                    const availableIconNames = getAvailableIconNames();
                                    const showIconPreview =
                                        isIconField && iconName !== '' && hasAppIcon(iconName);

                                    return (
                                        <AuthProviderField
                                            key={`${selectedProvider.key}-${field}`}
                                            label={meta.label}
                                            description={meta.description}
                                            error={errorMessage}
                                            required={
                                                field === 'display_name' || field === 'sort_order'
                                            }
                                            span={meta.span}
                                            className={
                                                field === 'icon'
                                                    ? 'app-field--icon'
                                                    : field === 'sort_order'
                                                      ? 'app-field--order'
                                                      : ''
                                            }
                                        >
                                            {isIconField ? (
                                                <div className="app-input-preview-row app-input-preview-row--compact">
                                                    <select
                                                        className={`app-input ${
                                                            errorMessage ? 'app-input--invalid' : ''
                                                        }`}
                                                        name={buildProviderInputName(
                                                            selectedProvider.key,
                                                            field,
                                                        )}
                                                        autoComplete="off"
                                                        value={form[field]}
                                                        onBlur={() => markFieldTouched(field)}
                                                        onChange={(event) =>
                                                            updateForm(field, event.target.value)
                                                        }
                                                    >
                                                        <option value="">
                                                            {t(
                                                                'adminAuth.iconPreview.selectPlaceholder',
                                                            )}
                                                        </option>
                                                        {availableIconNames.map((iconOption) => (
                                                            <option
                                                                key={iconOption}
                                                                value={iconOption}
                                                            >
                                                                {iconOption}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    <div
                                                        className={`app-icon-preview ${
                                                            showIconPreview
                                                                ? 'is-active'
                                                                : 'is-empty'
                                                        }`}
                                                    >
                                                        {showIconPreview ? (
                                                            <AppIcon name={iconName} />
                                                        ) : (
                                                            <span>
                                                                {t('adminAuth.iconPreview.empty')}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            ) : (
                                                <input
                                                    className={`app-input ${
                                                        errorMessage ? 'app-input--invalid' : ''
                                                    }`}
                                                    type={
                                                        field === 'sort_order' ? 'number' : 'text'
                                                    }
                                                    inputMode={
                                                        field === 'sort_order'
                                                            ? 'numeric'
                                                            : undefined
                                                    }
                                                    min={field === 'sort_order' ? '0' : undefined}
                                                    step={field === 'sort_order' ? '1' : undefined}
                                                    name={buildProviderInputName(
                                                        selectedProvider.key,
                                                        field,
                                                    )}
                                                    autoComplete="off"
                                                    data-lpignore="true"
                                                    data-1p-ignore="true"
                                                    value={form[field]}
                                                    placeholder={meta.placeholder}
                                                    onBlur={() => markFieldTouched(field)}
                                                    onChange={(event) =>
                                                        updateForm(field, event.target.value)
                                                    }
                                                />
                                            )}
                                            {isIconField ? (
                                                <p className="app-field__hint">
                                                    {t('adminAuth.iconPreview.availablePrefix')}{' '}
                                                    {availableIconNames.join(', ')}
                                                </p>
                                            ) : null}
                                        </AuthProviderField>
                                    );
                                })}

                                <AuthProviderField
                                    label={t('adminAuth.visibility.label')}
                                    description={t('adminAuth.visibility.help')}
                                    span="half"
                                >
                                    <select
                                        className="app-input"
                                        name={buildProviderInputName(
                                            selectedProvider.key,
                                            'visibility',
                                        )}
                                        autoComplete="off"
                                        value={form.visibility}
                                        onBlur={() => markFieldTouched('visibility')}
                                        onChange={(event) =>
                                            updateForm('visibility', event.target.value)
                                        }
                                    >
                                        <option value="public">
                                            {t('adminAuth.visibility.public')}
                                        </option>
                                        <option value="hidden">
                                            {t('adminAuth.visibility.hidden')}
                                        </option>
                                        <option value="admin_only">
                                            {t('adminAuth.visibility.adminOnly')}
                                        </option>
                                    </select>
                                </AuthProviderField>

                                {selectedProvider.key === 'email' ? (
                                    <AuthProviderField
                                        label={t('adminAuth.emailVerification.label')}
                                        description={t(
                                            'adminAuth.emailVerification.emailLockedHelp',
                                        )}
                                        span="full"
                                    >
                                        <input
                                            className="app-input"
                                            value={t('adminAuth.emailVerification.required')}
                                            disabled
                                            readOnly
                                        />
                                    </AuthProviderField>
                                ) : (
                                    <AuthProviderField
                                        label={t('adminAuth.emailVerification.label')}
                                        description={t('adminAuth.emailVerification.help')}
                                        span="full"
                                    >
                                        <select
                                            className="app-input"
                                            name={buildProviderInputName(
                                                selectedProvider.key,
                                                'email_verification_mode',
                                            )}
                                            autoComplete="off"
                                            value={form.email_verification_mode}
                                            onBlur={() =>
                                                markFieldTouched('email_verification_mode')
                                            }
                                            onChange={(event) =>
                                                updateForm(
                                                    'email_verification_mode',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                {t('adminAuth.emailVerification.inherit')}
                                            </option>
                                            <option value="required">
                                                {t('adminAuth.emailVerification.required')}
                                            </option>
                                            <option value="optional">
                                                {t('adminAuth.emailVerification.optional')}
                                            </option>
                                            <option value="disabled">
                                                {t('adminAuth.emailVerification.disabled')}
                                            </option>
                                        </select>
                                    </AuthProviderField>
                                )}
                            </div>
                        </section>

                        {Object.keys(form.public_config).length > 0 ? (
                            <section className="app-provider-section">
                                <div className="app-provider-section__head">
                                    <h3 className="app-form-card__title">
                                        {t('adminAuth.sections.public.title')}
                                    </h3>
                                    <p className="app-form-card__text">
                                        {t('adminAuth.sections.public.text')}
                                    </p>
                                </div>

                                <div className="app-provider-grid">
                                    {Object.keys(form.public_config).map((field) => {
                                        const meta = buildFieldMeta(t, field, {
                                            callbackUrl:
                                                selectedProvider.metadata?.callback_url ?? null,
                                            providerDisplayName: selectedProvider.display_name,
                                        });
                                        const errorMessage = getFieldError(
                                            fieldIssues,
                                            providerServerErrors,
                                            providerTouchedFields,
                                            `public_config.${field}`,
                                        );

                                        return (
                                            <AuthProviderField
                                                key={`${selectedProvider.key}-public-${field}`}
                                                label={meta.label}
                                                description={meta.description}
                                                error={errorMessage}
                                                required={(
                                                    selectedProvider.required_public_keys ?? []
                                                ).includes(field)}
                                                span={meta.span}
                                            >
                                                <input
                                                    className={`app-input ${
                                                        errorMessage ? 'app-input--invalid' : ''
                                                    }`}
                                                    name={buildProviderInputName(
                                                        selectedProvider.key,
                                                        field,
                                                        'public',
                                                    )}
                                                    autoComplete="off"
                                                    data-lpignore="true"
                                                    data-1p-ignore="true"
                                                    value={form.public_config[field]}
                                                    placeholder={meta.placeholder}
                                                    onBlur={() =>
                                                        markFieldTouched(`public_config.${field}`)
                                                    }
                                                    onChange={(event) =>
                                                        updateConfigField(
                                                            'public_config',
                                                            field,
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </AuthProviderField>
                                        );
                                    })}
                                </div>
                            </section>
                        ) : null}

                        {Object.keys(form.secret_config).length > 0 ? (
                            <section className="app-provider-section">
                                <div className="app-provider-section__head">
                                    <h3 className="app-form-card__title">
                                        {t('adminAuth.sections.secret.title')}
                                    </h3>
                                    <p className="app-form-card__text">
                                        {t('adminAuth.sections.secret.text')}
                                    </p>
                                </div>

                                <div className="app-provider-grid">
                                    {Object.keys(form.secret_config).map((field) => {
                                        const meta = buildFieldMeta(t, field);
                                        const errorMessage = getFieldError(
                                            fieldIssues,
                                            providerServerErrors,
                                            providerTouchedFields,
                                            `secret_config.${field}`,
                                        );
                                        const secretStateKey = `${selectedProvider.key}.${field}`;
                                        const hasStoredSecret = Boolean(
                                            selectedProvider.secret_status?.[field],
                                        );
                                        const hasTypedSecret =
                                            String(form.secret_config[field] ?? '').trim() !== '';
                                        const isEditingSecret =
                                            Boolean(secretEditState[secretStateKey]) ||
                                            hasTypedSecret;

                                        return (
                                            <AuthProviderField
                                                key={`${selectedProvider.key}-secret-${field}`}
                                                label={meta.label}
                                                description={meta.description}
                                                error={errorMessage}
                                                required={(
                                                    selectedProvider.required_secret_keys ?? []
                                                ).includes(field)}
                                                span={meta.span}
                                                badge={
                                                    selectedProvider.secret_status?.[field] ? (
                                                        <span className="app-field__badge">
                                                            {t('adminAuth.secretStored')}
                                                        </span>
                                                    ) : null
                                                }
                                            >
                                                {hasStoredSecret && !isEditingSecret ? (
                                                    <div className="app-secret-field">
                                                        <div className="app-secret-field__masked">
                                                            <span className="app-secret-field__masked-value">
                                                                ••••••••••••
                                                            </span>
                                                            <span className="app-secret-field__masked-label">
                                                                {t('adminAuth.secretStored')}
                                                            </span>
                                                        </div>
                                                        <div className="app-secret-field__actions">
                                                            <button
                                                                type="button"
                                                                className="app-secret-field__button"
                                                                onClick={() =>
                                                                    updateSecretEditState(
                                                                        field,
                                                                        true,
                                                                    )
                                                                }
                                                            >
                                                                {t('adminAuth.editSecret')}
                                                            </button>
                                                        </div>
                                                        <p className="app-field__hint">
                                                            {t('adminAuth.secretMaskedHint')}
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <div className="app-secret-field">
                                                        <SensitiveInput
                                                            value={form.secret_config[field]}
                                                            invalid={Boolean(errorMessage)}
                                                            name={buildProviderInputName(
                                                                selectedProvider.key,
                                                                field,
                                                                'secret',
                                                            )}
                                                            autoComplete="new-password"
                                                            placeholder={meta.placeholder}
                                                            revealLabel={t('auth.showValue')}
                                                            concealLabel={t('auth.hideValue')}
                                                            onBlur={() =>
                                                                markFieldTouched(
                                                                    `secret_config.${field}`,
                                                                )
                                                            }
                                                            onChange={(event) =>
                                                                updateConfigField(
                                                                    'secret_config',
                                                                    field,
                                                                    event.target.value,
                                                                )
                                                            }
                                                        />
                                                        {hasStoredSecret ? (
                                                            <div className="app-secret-field__actions">
                                                                <button
                                                                    type="button"
                                                                    className="app-secret-field__button app-secret-field__button--muted"
                                                                    onClick={() => {
                                                                        updateSecretEditState(
                                                                            field,
                                                                            false,
                                                                        );
                                                                        updateConfigField(
                                                                            'secret_config',
                                                                            field,
                                                                            '',
                                                                        );
                                                                    }}
                                                                >
                                                                    {t(
                                                                        'adminAuth.cancelSecretEdit',
                                                                    )}
                                                                </button>
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                )}
                                            </AuthProviderField>
                                        );
                                    })}
                                </div>
                            </section>
                        ) : null}

                        <section className="app-provider-section app-provider-section--footer">
                            {hasUnsavedChanges && validationErrors.length > 0 ? (
                                <div className="app-provider-note app-provider-note--warning">
                                    {t('adminAuth.footerValidationHint')}
                                </div>
                            ) : hasTouchedProvider && !hasUnsavedChanges ? (
                                <div className="app-provider-note app-provider-note--error">
                                    {t('adminAuth.noChangesToSave')}
                                </div>
                            ) : hasUnsavedChanges ? (
                                <div className="app-provider-note app-provider-note--success">
                                    {t('adminAuth.readyToSave')}
                                </div>
                            ) : null}

                            <div className="app-provider-actions">
                                {isEmailProvider ? (
                                    <div className="app-provider-lock">
                                        <span className="app-provider-lock__badge">
                                            {t('adminAuth.emailProvider.fixedBadge')}
                                        </span>
                                        <p className="app-provider-lock__text">
                                            {t('adminAuth.emailProvider.fixedText')}
                                        </p>
                                    </div>
                                ) : (
                                    <label className="app-switch">
                                        <input
                                            type="checkbox"
                                            checked={form.enabled}
                                            onChange={(event) =>
                                                setConfirmState({
                                                    type: 'toggle',
                                                    nextEnabled: event.target.checked,
                                                })
                                            }
                                        />
                                        <span className="app-switch__track">
                                            <span className="app-switch__thumb" />
                                        </span>
                                        <span className="app-switch__text">
                                            {form.enabled
                                                ? t('adminAuth.status.enabled')
                                                : t('adminAuth.status.disabled')}
                                        </span>
                                    </label>
                                )}
                                <button
                                    type="button"
                                    className="app-button app-button--primary"
                                    onClick={() => setConfirmState({ type: 'save' })}
                                    disabled={!canSave}
                                >
                                    {savingKey === selectedProvider.key
                                        ? t('adminAuth.saving')
                                        : t('adminAuth.saveButton')}
                                </button>
                            </div>
                        </section>
                    </form>
                )}
            </section>

            <ConfirmModal
                open={Boolean(confirmState)}
                eyebrow={t('adminAuth.modal.eyebrow')}
                title={
                    confirmState?.type === 'save'
                        ? t('adminAuth.modal.saveTitle')
                        : confirmState?.nextEnabled
                          ? t('adminAuth.modal.enableTitle')
                          : t('adminAuth.modal.disableTitle')
                }
                text={
                    confirmState?.type === 'save'
                        ? t('adminAuth.modal.saveText')
                        : confirmState?.nextEnabled
                          ? `${t('adminAuth.confirm.prefix')} "${selectedProvider.display_name}" ${t(
                                'adminAuth.confirm.enableSuffix',
                            )}`
                          : isLastActiveProvider
                            ? t('adminAuth.lastProviderText')
                            : `${t('adminAuth.confirm.prefix')} "${selectedProvider.display_name}" ${t(
                                  'adminAuth.confirm.disableSuffix',
                              )}`
                }
                confirmLabel={
                    confirmState?.type === 'save'
                        ? t('adminAuth.modal.confirmSave')
                        : confirmState?.nextEnabled
                          ? t('adminAuth.modal.confirmEnable')
                          : t('adminAuth.modal.confirmDisable')
                }
                cancelLabel={t('common.cancel')}
                tone={
                    confirmState?.type === 'save'
                        ? 'primary'
                        : confirmState?.nextEnabled
                          ? 'primary'
                          : 'danger'
                }
                onCancel={() => setConfirmState(null)}
                onConfirm={() => {
                    if (!confirmState) {
                        return;
                    }

                    if (confirmState.type === 'toggle') {
                        if (isLastActiveProvider && !confirmState.nextEnabled) {
                            setFlash({
                                type: 'error',
                                message: t('adminAuth.lastProviderText'),
                            });
                            setConfirmState(null);

                            return;
                        }

                        updateForm('enabled', confirmState.nextEnabled);
                        setConfirmState(null);

                        return;
                    }

                    setConfirmState(null);
                    void performSaveProvider();
                }}
            />
        </div>
    );
}
