import AuthProviderPublicConfigField from './AuthProviderPublicConfigField';

/**
 * Render non-secret provider configuration fields.
 */
export default function AuthProviderPublicConfigSection({
    provider,
    form,
    fieldIssues,
    serverErrors,
    touchedFields,
    onFieldBlur,
    onConfigChange,
    t,
}) {
    const fields = Object.keys(form.public_config);

    if (fields.length === 0) {
        return null;
    }

    return (
        <section className="app-provider-section">
            <div className="app-provider-section__head">
                <h3 className="app-form-card__title">{t('adminAuth.sections.public.title')}</h3>
                <p className="app-form-card__text">{t('adminAuth.sections.public.text')}</p>
            </div>

            <div className="app-provider-grid">
                {fields.map((field) => (
                    <AuthProviderPublicConfigField
                        key={`${provider.key}-public-${field}`}
                        provider={provider}
                        form={form}
                        field={field}
                        fieldIssues={fieldIssues}
                        serverErrors={serverErrors}
                        touchedFields={touchedFields}
                        onFieldBlur={onFieldBlur}
                        onConfigChange={onConfigChange}
                        t={t}
                    />
                ))}
            </div>
        </section>
    );
}
