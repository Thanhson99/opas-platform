import { useLocation, useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import PageHero from '../../../components/ui/PageHero';
import { useAuth } from '../../auth/context/AuthContext';
import { useLanguage } from '../../i18n/context/LanguageContext';
import AlertListCard from '../components/AlertListCard';
import AlertMetrics from '../components/AlertMetrics';
import { useCoinAlerts } from '../hooks/useCoinAlerts';

/**
 * Render the price-alert list with quick toggle and edit actions.
 */
export default function AlertsPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { isAuthenticated } = useAuth();
    const { t } = useLanguage();
    const { alerts, metrics, loading, error, toggleAlert } = useCoinAlerts({
        loadErrorText: t('alertsPage.loadError'),
        toggleErrorText: t('alertsPage.toggleError'),
    });

    const toggleAlertStatus = async (id) => {
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        await toggleAlert(id);
    };

    const getEditLink = (alert) => {
        if (!isAuthenticated) {
            return {
                to: '/login',
                state: { from: location },
            };
        }

        return {
            to: `/coins/price-alert-settings/${alert.id}/edit`,
        };
    };

    if (loading) return <LoadingState text={t('alertsPage.loading')} />;
    if (error) return <ErrorState text={error} />;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow={t('alertsPage.hero.eyebrow')}
                title={t('alertsPage.hero.title')}
                text={t('alertsPage.hero.text')}
            >
                <span className="app-chip">{t('alertsPage.hero.toggleChip')}</span>
                <span className="app-chip">{t('alertsPage.hero.editChip')}</span>
            </PageHero>

            <AlertMetrics metrics={metrics} t={t} />
            <AlertListCard
                alerts={alerts}
                getEditLink={getEditLink}
                t={t}
                onToggle={toggleAlertStatus}
            />
        </div>
    );
}
