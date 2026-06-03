import AuthProviderSecretField from './AuthProviderSecretField';

/**
 * Render masked and editable secret provider configuration fields.
 */
export default function AuthProviderSecretConfigSection({
    provider,
    form,
    fieldIssues,
    serverErrors,
    touchedFields,
    secretEditState,
    onFieldBlur,
    onConfigChange,
    onSecretEditChange,
    t,
}) {
    const fields = Object.keys(form.secret_config);

    if (fields.length === 0) {
        return null;
    }

    return (
        <section className="app-provider-section">
            <div className="app-provider-section__head">
                <h3 className="app-form-card__title">{t('adminAuth.sections.secret.title')}</h3>
                <p className="app-form-card__text">{t('adminAuth.sections.secret.text')}</p>
            </div>

            <div className="app-provider-grid">
                {fields.map((field) => (
                    <AuthProviderSecretField
                        key={`${provider.key}-secret-${field}`}
                        provider={provider}
                        form={form}
                        field={field}
                        fieldIssues={fieldIssues}
                        serverErrors={serverErrors}
                        touchedFields={touchedFields}
                        secretEditState={secretEditState}
                        onFieldBlur={onFieldBlur}
                        onConfigChange={onConfigChange}
                        onSecretEditChange={onSecretEditChange}
                        t={t}
                    />
                ))}
            </div>
        </section>
    );
}
