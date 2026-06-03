import { memo } from 'react';
import AppIcon, { hasAppIcon } from '../../../../components/icons/AppIcon';

/**
 * Render the selected provider identity, summary, and status chips.
 */
function AuthProviderAdminHeader({ provider, form, statusTone, visibilityLabel, summary, t }) {
    return (
        <>
            <div className="app-provider-page__head">
                <p className="app-provider-card__eyebrow">{provider.key}</p>
                <div className="app-provider-page__title-row">
                    {hasAppIcon(form.icon) ? (
                        <span className="app-provider-page__icon">
                            <AppIcon name={form.icon} />
                        </span>
                    ) : null}
                    <h1 className="app-provider-page__title">
                        {form.display_name || provider.display_name}
                    </h1>
                </div>
                <p className="app-provider-page__text">{summary}</p>
            </div>

            <div className="app-chip-row">
                <span className={`app-status-pill ${statusTone}`}>
                    {provider.active
                        ? t('adminAuth.status.live')
                        : provider.ready
                          ? t('adminAuth.status.ready')
                          : t('adminAuth.status.incomplete')}
                </span>
                <span className="app-chip">{provider.type}</span>
                <span className="app-chip">
                    {t('adminAuth.visibility.chip')} {visibilityLabel}
                </span>
            </div>
        </>
    );
}

export default memo(AuthProviderAdminHeader);
