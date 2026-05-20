import { useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import AppIcon from '../../../components/icons/AppIcon';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import MetricCard from '../../../components/ui/MetricCard';
import PageHero from '../../../components/ui/PageHero';
import { useAuth } from '../../auth/context/AuthContext';
import api from '../../../lib/api';

/**
 * Render the stock-monitor workspace with search and favorites.
 */
export default function StocksPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { isAuthenticated } = useAuth();
    const [stocks, setStocks] = useState([]);
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const loadStocks = async () => {
        setLoading(true);
        try {
            const response = await api.get('/stocks');
            setStocks(response.data.data ?? []);
            setError('');
        } catch {
            setError('Unable to load stock data.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void loadStocks();
    }, []);

    const filtered = useMemo(() => {
        const needle = query.toLowerCase().trim();
        const source = !needle
            ? stocks
            : stocks.filter((stock) =>
                  [stock.symbol, stock.name, stock.exchange]
                      .join(' ')
                      .toLowerCase()
                      .includes(needle),
              );

        return [...source].sort((left, right) => {
            if (left.is_favorite === right.is_favorite) {
                return left.symbol.localeCompare(right.symbol);
            }

            return left.is_favorite ? -1 : 1;
        });
    }, [query, stocks]);

    const toggleFavorite = async (symbol) => {
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        const stock = stocks.find((item) => item.symbol === symbol);

        if (!stock) {
            return;
        }

        if (stock.is_favorite) {
            await api.delete(`/stocks/favorites/${symbol}`);
        } else {
            await api.put(`/stocks/favorites/${symbol}`);
        }

        await loadStocks();
    };

    if (loading) return <LoadingState text="Loading stocks..." />;
    if (error) return <ErrorState text={error} />;

    const exchanges = new Set(filtered.map((stock) => stock.exchange).filter(Boolean)).size;
    const favorites = filtered.filter((stock) => stock.is_favorite).length;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow="Stock Monitor"
                title="Watchlist cổ phiếu sáng hơn, rõ hơn và dễ scan hơn."
                text="Danh sách cổ phiếu được giữ gọn để tra cứu nhanh theo mã, công ty hoặc sàn, đồng thời vẫn có vùng watchlist để giữ nhịp theo dõi."
            >
                <span className="app-chip">Search theo symbol, name, exchange</span>
                <span className="app-chip">Favorite list cục bộ qua API</span>
            </PageHero>

            <section className="app-metrics-grid">
                <MetricCard
                    label="Filtered stocks"
                    value={filtered.length}
                    hint="Số mã theo kết quả search hiện tại"
                    tone="sky"
                />
                <MetricCard
                    label="Exchanges"
                    value={exchanges}
                    hint="Số sàn xuất hiện trong danh sách đang xem"
                    tone="violet"
                />
                <MetricCard
                    label="Favorites"
                    value={favorites}
                    hint="Watchlist trong nhóm đang lọc"
                    tone="amber"
                />
            </section>

            <section className="app-surface">
                <div className="app-surface__header">
                    <div>
                        <h2 className="app-surface__title">Stocks</h2>
                        <p className="app-surface__text">
                            Theo dõi cổ phiếu Việt Nam, tìm kiếm nhanh và giữ lại watchlist.
                        </p>
                    </div>
                </div>
                <div className="app-search-row">
                    <input
                        className="app-input"
                        placeholder="Search by symbol, name, exchange..."
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>
                <div className="app-table-wrap">
                    <table className="app-table">
                        <thead>
                            <tr>
                                <th>Symbol</th>
                                <th>Company</th>
                                <th>Exchange</th>
                                <th>Favorite</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((stock) => (
                                <tr key={stock.symbol}>
                                    <td>{stock.symbol}</td>
                                    <td>{stock.name}</td>
                                    <td>{stock.exchange}</td>
                                    <td>
                                        <button
                                            type="button"
                                            className="app-favorite"
                                            onClick={() => toggleFavorite(stock.symbol)}
                                            aria-label={`Toggle favorite for ${stock.symbol}`}
                                        >
                                            <AppIcon
                                                name="heart"
                                                filled={stock.is_favorite}
                                                className={
                                                    stock.is_favorite
                                                        ? 'is-favorite'
                                                        : 'is-favorite-muted'
                                                }
                                            />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}
