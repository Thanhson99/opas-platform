import ErrorState from '../../../../components/ui/ErrorState';
import LoadingState from '../../../../components/ui/LoadingState';
import PageHero from '../../../../components/ui/PageHero';
import AdminAuthProviderGrid from '../../components/AdminAuthProviderGrid';
import { useAdminAuthProviders } from '../../hooks/useAdminAuthProviders';
import { useLanguage } from '../../../i18n/context/LanguageContext';

/**
 * Render the provider dashboard used to jump into one auth-provider config screen.
 */
export default function AuthProvidersDashboardPage() {
    const { t } = useLanguage();
    const { providers, loading, error } = useAdminAuthProviders({
        loadErrorText: t('adminAuth.loadError'),
    });

    if (loading) {
        return <LoadingState text={t('adminAuth.loading')} />;
    }

    if (error) {
        return <ErrorState text={error} />;
    }

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

            <AdminAuthProviderGrid providers={providers} t={t} />
        </div>
    );
}
