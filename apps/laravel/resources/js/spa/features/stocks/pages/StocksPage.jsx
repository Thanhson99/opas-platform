import { useLocation, useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import { useAuth } from '../../auth/context/AuthContext';
import { useLanguage } from '../../i18n/context/LanguageContext';
import StockMarketHero from '../components/StockMarketHero';
import StockMarketMetrics from '../components/StockMarketMetrics';
import StockMarketTable from '../components/StockMarketTable';
import { useStocksMarket } from '../hooks/useStocksMarket';

/**
 * Render the stock-monitor workspace with search and favorites.
 */
export default function StocksPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { isAuthenticated } = useAuth();
    const { t } = useLanguage();
    const { query, setQuery, filteredStocks, metrics, loading, error, toggleFavorite } =
        useStocksMarket({
            loadErrorText: t('stocksPage.loadError'),
        });

    const handleFavoriteToggle = async (symbol) => {
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        await toggleFavorite(symbol);
    };

    if (loading) return <LoadingState text={t('stocksPage.loading')} />;
    if (error) return <ErrorState text={error} />;

    return (
        <div className="app-shell">
            <StockMarketHero t={t} />
            <StockMarketMetrics filteredCount={filteredStocks.length} metrics={metrics} t={t} />
            <StockMarketTable
                query={query}
                stocks={filteredStocks}
                t={t}
                onFavoriteToggle={handleFavoriteToggle}
                onQueryChange={setQuery}
            />
        </div>
    );
}
