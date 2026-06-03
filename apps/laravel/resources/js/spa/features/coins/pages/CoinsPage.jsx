import { useLocation, useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import { useAuth } from '../../auth/context/AuthContext';
import { useLanguage } from '../../i18n/context/LanguageContext';
import CoinMarketHero from '../components/CoinMarketHero';
import CoinMarketMetrics from '../components/CoinMarketMetrics';
import CoinMarketTable from '../components/CoinMarketTable';
import { useCoinsMarket } from '../hooks/useCoinsMarket';

/**
 * Render the main coin-monitor workspace with favorites and market summaries.
 */
export default function CoinsPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { isAuthenticated } = useAuth();
    const { t } = useLanguage();
    const { sortedCoins, summary, loading, error, toggleFavorite } = useCoinsMarket({
        loadErrorText: t('coinsPage.loadError'),
    });

    const handleFavoriteToggle = async (symbol) => {
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        await toggleFavorite(symbol);
    };

    if (loading) return <LoadingState text={t('coinsPage.loading')} />;
    if (error) return <ErrorState text={error} />;

    return (
        <div className="app-shell">
            <CoinMarketHero summary={summary} t={t} />
            <CoinMarketMetrics summary={summary} t={t} />
            <CoinMarketTable coins={sortedCoins} t={t} onFavoriteToggle={handleFavoriteToggle} />
        </div>
    );
}
