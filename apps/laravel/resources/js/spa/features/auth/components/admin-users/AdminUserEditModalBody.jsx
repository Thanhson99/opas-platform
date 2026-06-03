import { memo, useCallback, useMemo } from 'react';
import AppIcon, { hasAppIcon } from '../../../../components/icons/AppIcon';

/**
 * Render admin user edit modal body content.
 *
 * @param {{
 *   error: string,
 *   form: Record<string, string>,
 *   t: (key: string) => string,
 *   user: Record<string, unknown>,
 *   onChange: (field: string, value: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AdminUserEditModalBody({ error, form, t, user, onChange }) {
    return (
        <div className="app-modal__body app-user-edit-modal">
            <AdminUserEditModalHero t={t} />
            <AdminUserEditForm error={error} form={form} t={t} user={user} onChange={onChange} />
            <AdminUserEditMetaGrid t={t} user={user} />
            {error ? <p className="app-field__error">{error}</p> : null}
        </div>
    );
}

export default memo(AdminUserEditModalBody);

const AdminUserEditModalHero = memo(function AdminUserEditModalHero({ t }) {
    return (
        <div className="app-user-edit-modal__hero">
            <p className="app-modal__eyebrow">{t('adminUsers.editModal.eyebrow')}</p>
            <h3 className="app-modal__title" id="admin-user-edit-modal-title">
                {t('adminUsers.editModal.title')}
            </h3>
            <p className="app-modal__text">{t('adminUsers.editModal.text')}</p>
        </div>
    );
});

const AdminUserEditForm = memo(function AdminUserEditForm({ error, form, t, user, onChange }) {
    const handleNameChange = useCallback(
        (event) => {
            onChange('name', event.target.value);
        },
        [onChange],
    );
    const handleRoleChange = useCallback(
        (event) => {
            onChange('role', event.target.value);
        },
        [onChange],
    );

    return (
        <div className="app-user-edit-modal__panel">
            <div className="app-form-grid app-user-edit-modal__grid">
                <label className="app-field">
                    <span className="app-field__label">{t('adminUsers.columns.name')}</span>
                    <input
                        type="text"
                        className={`app-input ${error ? 'app-input--invalid' : ''}`}
                        value={form.name}
                        aria-invalid={Boolean(error)}
                        onChange={handleNameChange}
                    />
                </label>

                <label className="app-field">
                    <span className="app-field__label">{t('adminUsers.columns.email')}</span>
                    <input
                        type="email"
                        className="app-input"
                        value={form.email}
                        disabled
                        readOnly
                    />
                </label>

                <label className="app-field">
                    <span className="app-field__label">{t('adminUsers.columns.role')}</span>
                    <select
                        className={`app-input ${error ? 'app-input--invalid' : ''}`}
                        value={form.role}
                        disabled={Boolean(user.is_current_user)}
                        aria-invalid={Boolean(error)}
                        onChange={handleRoleChange}
                    >
                        {(user.available_roles ?? []).map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>
                    {user.is_current_user ? (
                        <span className="app-field__help">{t('adminUsers.selfRoleLocked')}</span>
                    ) : null}
                </label>
            </div>
        </div>
    );
});

const AdminUserEditMetaGrid = memo(function AdminUserEditMetaGrid({ t, user }) {
    return (
        <div className="app-user-edit-modal__meta-grid">
            <AdminUserStatusMetaCard t={t} user={user} />
            <AdminUserProvidersMetaCard t={t} user={user} />
            <AdminUserVerifiedAtMetaCard t={t} user={user} />
        </div>
    );
});

const AdminUserStatusMetaCard = memo(function AdminUserStatusMetaCard({ t, user }) {
    return (
        <div className="app-user-edit-modal__meta-card">
            <span className="app-user-edit-modal__meta-label">
                {t('adminUsers.columns.status')}
            </span>
            <span
                className={`app-status-pill ${
                    user.email_verified ? 'app-status-pill--success' : 'app-status-pill--warning'
                }`}
            >
                {user.email_verified
                    ? t('adminUsers.status.verified')
                    : t('adminUsers.status.unverified')}
            </span>
        </div>
    );
});

const AdminUserProvidersMetaCard = memo(function AdminUserProvidersMetaCard({ t, user }) {
    const providers = useMemo(() => user.linked_providers ?? [], [user.linked_providers]);
    const providerItems = useMemo(
        () =>
            providers.map((provider) => ({
                ...provider,
                iconName: getProviderIconName(provider),
            })),
        [providers],
    );

    return (
        <div className="app-user-edit-modal__meta-card">
            <span className="app-user-edit-modal__meta-label">
                {t('adminUsers.columns.providers')}
            </span>
            <div className="app-user-edit-modal__providers">
                {providers.length > 0 ? (
                    providerItems.map((provider) => (
                        <span key={provider.key} className="app-user-edit-modal__provider-chip">
                            <AppIcon name={provider.iconName} />
                            <span>{provider.display_name}</span>
                        </span>
                    ))
                ) : (
                    <span className="app-user-edit-modal__provider-empty">
                        <AppIcon name="link" />
                        <span>{t('adminUsers.noLinkedProviders')}</span>
                    </span>
                )}
            </div>
        </div>
    );
});

function getProviderIconName(provider) {
    const configuredIcon = provider.icon ?? provider.key;

    return hasAppIcon(configuredIcon) ? configuredIcon : 'link';
}

const AdminUserVerifiedAtMetaCard = memo(function AdminUserVerifiedAtMetaCard({ t, user }) {
    return (
        <div className="app-user-edit-modal__meta-card">
            <span className="app-user-edit-modal__meta-label">
                {t('adminUsers.columns.verifiedAt')}
            </span>
            <span className="app-user-edit-modal__value">
                {user.email_verified_at
                    ? new Date(user.email_verified_at).toLocaleString()
                    : t('adminUsers.notAvailable')}
            </span>
        </div>
    );
});
