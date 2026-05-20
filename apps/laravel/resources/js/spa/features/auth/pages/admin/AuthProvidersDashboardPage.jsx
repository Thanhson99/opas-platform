import { useEffect, useState } from 'react';
import { NavLink } from 'react-router-dom';
import AppIcon, { hasAppIcon } from '../../../../components/icons/AppIcon';
import ErrorState from '../../../../components/ui/ErrorState';
import LoadingState from '../../../../components/ui/LoadingState';
import PageHero from '../../../../components/ui/PageHero';
import api from '../../../../lib/api';
import { useLanguage } from '../../../i18n/context/LanguageContext';

/**
 * Render the provider dashboard used to jump into one auth-provider config screen.
 */
export default function AuthProvidersDashboardPage() {
    const { t } = useLanguage();
    const [providers, setProviders] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        const loadProviders = async () => {
            setLoading(true);

            try {
                const response = await api.get('/admin/auth/providers');
                setProviders(response.data.data ?? []);
                setError('');
            } catch (requestError) {
                setProviders([]);
                setError(requestError?.response?.data?.message || t('adminAuth.loadError'));
            } finally {
                setLoading(false);
            }
        };

        void loadProviders();
    }, [t]);

    if (loading) {
        return <LoadingState text={t('adminAuth.loading')} />;
    }

    if (error) {
        return <ErrorState text={error} />;
    }

    const getProviderSummary = (provider) => {
        const summaryKey = `adminAuth.providers.${provider.key}.summary`;
        const summary = t(summaryKey);

        if (summary !== summaryKey) {
            return summary;
        }

        return t('adminAuth.providers.default.summary');
    };

    return (
        <div className="app-shell">
            <PageHero
                eyebrow={t('adminAuth.dashboard.eyebrow')}
                title={t('adminAuth.dashboard.title')}
                text={t('adminAuth.dashboard.text')}
            >
                <span className="app-chip">{t('adminAuth.hero.chipSecrets')}</span>
                <span className="app-chip">{t('adminAuth.hero.chipReadiness')}</span>
            </PageHero>

            <section className="app-provider-switcher">
                <div className="app-provider-switcher__head">
                    <h2 className="app-form-card__title">{t('adminAuth.dashboard.quickTitle')}</h2>
                    <p className="app-form-card__text">{t('adminAuth.dashboard.quickText')}</p>
                </div>

                <div className="app-provider-dashboard-grid">
                    {providers.map((provider) => (
                        <NavLink
                            key={provider.key}
                            to={`/admin/auth/providers/${provider.key}`}
                            className="app-provider-dashboard-card"
                        >
                            <span className="app-provider-dashboard-card__icon">
                                {hasAppIcon(provider.icon) ? (
                                    <AppIcon name={provider.icon} />
                                ) : null}
                            </span>
                            <strong>{provider.display_name}</strong>
                            <span>{getProviderSummary(provider)}</span>
                        </NavLink>
                    ))}
                </div>
            </section>
        </div>
    );
}
