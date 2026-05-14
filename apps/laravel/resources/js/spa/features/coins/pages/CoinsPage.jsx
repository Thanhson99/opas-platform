import { useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import AppIcon from '../../../components/icons/AppIcon';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import MetricCard from '../../../components/ui/MetricCard';
import PageHero from '../../../components/ui/PageHero';
import { useAuth } from '../../auth/context/AuthContext';
import api from '../../../lib/api';

const compactCurrency = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 2,
});

const money = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 3,
});

export default function CoinsPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { isAuthenticated } = useAuth();
    const [coins, setCoins] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const loadCoins = async () => {
        setLoading(true);
        try {
            const response = await api.get('/coins');
            setCoins(response.data.data ?? []);
            setError('');
        } catch {
            setError('Unable to load coin data.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void loadCoins();
    }, []);

    const summary = useMemo(() => {
        const sorted = [...coins].sort((left, right) => {
            if (left.is_favorite === right.is_favorite) {
                return Number(right.quoteVolume ?? 0) - Number(left.quoteVolume ?? 0);
            }

            return left.is_favorite ? -1 : 1;
        });
        const favorites = sorted.filter((coin) => coin.is_favorite).length;
        const positive = sorted.filter((coin) => Number(coin.priceChangePercent ?? 0) >= 0).length;
        const topMover = [...sorted].sort(
            (left, right) =>
                Number(right.priceChangePercent ?? 0) - Number(left.priceChangePercent ?? 0),
        )[0];

        return {
            sorted,
            count: coins.length,
            favorites,
            positive,
            topMover,
        };
    }, [coins]);

    const toggleFavorite = async (symbol) => {
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        const coin = coins.find((item) => item.symbol === symbol);

        if (!coin) {
            return;
        }

        if (coin.is_favorite) {
            await api.delete(`/coins/favorites/${symbol}`);
        } else {
            await api.put(`/coins/favorites/${symbol}`);
        }

        await loadCoins();
    };

    if (loading) return <LoadingState text="Loading coins..." />;
    if (error) return <ErrorState text={error} />;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow="Coin Monitor"
                title="Theo dõi coin theo kiểu workspace, không phải bảng khô."
                text="Danh sách được giữ đơn giản để nhìn nhanh, nhưng vẫn có đủ watchlist, chi tiết mã và biến động để thao tác trong ngày."
                actions={
                    <>
                        <Link
                            to="/coins/price-alert-settings"
                            className="app-button app-button--primary"
                        >
                            Mở Price Alerts
                        </Link>
                        <Link to="/coins/feed-keywords" className="app-button app-button--ghost">
                            Mở Keywords
                        </Link>
                    </>
                }
                aside={
                    summary.topMover ? (
                        <div className="app-hero-card app-hero-card--compact">
                            <p className="app-hero-card__eyebrow">Top mover</p>
                            <h3 className="app-hero-card__title">{summary.topMover.symbol}</h3>
                            <p className="app-hero-card__text">
                                Biến động hiện tại{' '}
                                {Number(summary.topMover.priceChangePercent ?? 0).toFixed(2)}%
                            </p>
                        </div>
                    ) : null
                }
            >
                <span className="app-chip">Realtime source qua API service</span>
                <span className="app-chip">Favorite toggle RESTful</span>
            </PageHero>

            <section className="app-metrics-grid">
                <MetricCard
                    label="Tracked coins"
                    value={summary.count}
                    hint="Số mã đang hiển thị trong market feed"
                    tone="sky"
                />
                <MetricCard
                    label="Favorites"
                    value={summary.favorites}
                    hint="Danh sách ưu tiên theo dõi"
                    tone="amber"
                />
                <MetricCard
                    label="Positive movers"
                    value={summary.positive}
                    hint="Mã đang có phần trăm thay đổi dương"
                    tone="mint"
                />
            </section>

            <section className="app-surface">
                <div className="app-surface__header">
                    <div>
                        <h2 className="app-surface__title">Coins</h2>
                        <p className="app-surface__text">
                            Theo dõi các mã coin nổi bật và đánh dấu danh sách cần quan sát.
                        </p>
                    </div>
                </div>
                <div className="app-inline-stats">
                    <span className="app-inline-badge">Click symbol để xem detail</span>
                    <span className="app-inline-badge">Heart để thêm watchlist</span>
                </div>
                <div className="app-table-wrap app-table-wrap--wide">
                    <table className="app-table app-table--coins">
                        <colgroup>
                            <col className="app-table__col-symbol" />
                            <col className="app-table__col-price" />
                            <col className="app-table__col-volume" />
                            <col className="app-table__col-change" />
                            <col className="app-table__col-favorite" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Symbol</th>
                                <th>Price</th>
                                <th>Volume</th>
                                <th>Change</th>
                                <th className="app-table__align-center">Favorite</th>
                            </tr>
                        </thead>
                        <tbody>
                            {summary.sorted.map((coin) => (
                                <tr key={coin.symbol}>
                                    <td>
                                        <Link
                                            className="app-link"
                                            to={`/coins/show/${coin.symbol}`}
                                        >
                                            {coin.symbol}
                                        </Link>
                                    </td>
                                    <td className="app-table__value-strong">
                                        ${money.format(Number(coin.lastPrice ?? 0))}
                                    </td>
                                    <td className="app-table__value-soft">
                                        ${compactCurrency.format(Number(coin.quoteVolume ?? 0))}
                                    </td>
                                    <td>
                                        <span
                                            className={`app-change-pill ${
                                                Number(coin.priceChangePercent ?? 0) >= 0
                                                    ? 'is-positive'
                                                    : 'is-negative'
                                            }`}
                                        >
                                            {Number(coin.priceChangePercent ?? 0).toFixed(2)}%
                                        </span>
                                    </td>
                                    <td className="app-table__align-center">
                                        <button
                                            type="button"
                                            className={`app-favorite ${
                                                coin.is_favorite ? 'is-active' : ''
                                            }`}
                                            onClick={() => toggleFavorite(coin.symbol)}
                                            aria-label={`Toggle favorite for ${coin.symbol}`}
                                        >
                                            <AppIcon
                                                name="heart"
                                                filled={coin.is_favorite}
                                                className={
                                                    coin.is_favorite
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
