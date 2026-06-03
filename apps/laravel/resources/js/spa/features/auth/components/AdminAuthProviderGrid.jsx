import { memo, useMemo } from 'react';
import { NavLink } from 'react-router-dom';
import AppIcon, { hasAppIcon } from '../../../components/icons/AppIcon';

function getProviderSummary(provider, t) {
    const summaryKey = `adminAuth.providers.${provider.key}.summary`;
    const summary = t(summaryKey);

    if (summary !== summaryKey) {
        return summary;
    }

    return t('adminAuth.providers.default.summary');
}

/**
 * Render admin auth-provider navigation cards.
 *
 * @param {{ providers: Array<Record<string, unknown>>, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
function AdminAuthProviderGrid({ providers, t }) {
    const providerCards = useMemo(
        () =>
            providers.map((provider) => ({
                ...provider,
                iconName: hasAppIcon(provider.icon) ? provider.icon : 'shield',
                summary: getProviderSummary(provider, t),
            })),
        [providers, t],
    );

    return (
        <section className="app-provider-switcher">
            <div className="app-provider-switcher__head">
                <h2 className="app-form-card__title">{t('adminAuth.dashboard.quickTitle')}</h2>
                <p className="app-form-card__text">{t('adminAuth.dashboard.quickText')}</p>
            </div>

            <div className="app-provider-dashboard-grid">
                {providerCards.map((provider) => (
                    <NavLink
                        key={provider.key}
                        to={`/admin/auth/providers/${provider.key}`}
                        className="app-provider-dashboard-card"
                    >
                        <span className="app-provider-dashboard-card__icon">
                            <AppIcon name={provider.iconName} />
                        </span>
                        <strong>{provider.display_name}</strong>
                        <span>{provider.summary}</span>
                    </NavLink>
                ))}
            </div>
        </section>
    );
}

export default memo(AdminAuthProviderGrid);
