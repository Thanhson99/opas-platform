/**
 * Render account profile details and editable display name.
 *
 * @param {{
 *   flash: string,
 *   formError: string,
 *   name: string,
 *   nameChanged: boolean,
 *   saving: boolean,
 *   t: (key: string) => string,
 *   user: Record<string, unknown>,
 *   onNameChange: (name: string) => void,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function AccountProfileSection({
    flash,
    formError,
    name,
    nameChanged,
    saving,
    t,
    user,
    onNameChange,
    onSubmit,
}) {
    return (
        <section
            id="account-profile"
            className="app-form-card app-form-card--accent app-account-settings"
        >
            <div className="app-account-settings__section-head">
                <div>
                    <h3 className="app-form-card__title">{t('accountSettings.profileTitle')}</h3>
                    <p className="app-form-card__text">{t('accountSettings.profileText')}</p>
                </div>
                {flash ? <p className="app-account-settings__flash">{flash}</p> : null}
            </div>

            <form className="app-form" onSubmit={onSubmit}>
                <div className="app-form-grid app-account-settings__grid">
                    <div className="app-field">
                        <label className="app-label" htmlFor="account-name">
                            {t('auth.name')}
                        </label>
                        <input
                            id="account-name"
                            className="app-input"
                            value={name}
                            onChange={(event) => onNameChange(event.target.value)}
                            maxLength={255}
                        />
                    </div>
                    <div className="app-field">
                        <label className="app-label" htmlFor="account-email">
                            {t('auth.email')}
                        </label>
                        <input
                            id="account-email"
                            className="app-input"
                            value={user.email ?? ''}
                            disabled
                        />
                        <p className="app-field__hint">{t('accountSettings.emailLocked')}</p>
                    </div>
                </div>

                {formError ? <p className="app-field__error">{formError}</p> : null}

                <div className="app-action-row">
                    <button
                        type="submit"
                        className="app-button app-button--primary app-account-settings__submit"
                        disabled={saving || !name.trim() || !nameChanged}
                    >
                        {saving ? t('accountSettings.saving') : t('accountSettings.save')}
                    </button>
                </div>
            </form>
        </section>
    );
}
