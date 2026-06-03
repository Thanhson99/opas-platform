import AuthProviderBaseField from './AuthProviderBaseField';
import AuthProviderEmailVerificationField from './AuthProviderEmailVerificationField';
import AuthProviderVisibilityField from './AuthProviderVisibilityField';

const BASE_FIELDS = ['display_name', 'icon', 'sort_order'];

/**
 * Render editable base settings for one admin-managed auth provider.
 */
export default function AuthProviderBasicSection({
    provider,
    form,
    fieldIssues,
    serverErrors,
    touchedFields,
    onFieldBlur,
    onFieldChange,
    t,
}) {
    return (
        <section className="app-provider-section">
            <div className="app-provider-section__head">
                <h3 className="app-form-card__title">{t('adminAuth.sections.basic.title')}</h3>
                <p className="app-form-card__text">{t('adminAuth.sections.basic.text')}</p>
            </div>

            <div className="app-provider-grid">
                {BASE_FIELDS.map((field) => (
                    <AuthProviderBaseField
                        key={`${provider.key}-${field}`}
                        provider={provider}
                        form={form}
                        field={field}
                        fieldIssues={fieldIssues}
                        serverErrors={serverErrors}
                        touchedFields={touchedFields}
                        onFieldBlur={onFieldBlur}
                        onFieldChange={onFieldChange}
                        t={t}
                    />
                ))}

                <AuthProviderVisibilityField
                    provider={provider}
                    value={form.visibility}
                    onFieldBlur={onFieldBlur}
                    onFieldChange={onFieldChange}
                    t={t}
                />

                <AuthProviderEmailVerificationField
                    provider={provider}
                    value={form.email_verification_mode}
                    onFieldBlur={onFieldBlur}
                    onFieldChange={onFieldChange}
                    t={t}
                />
            </div>
        </section>
    );
}
