import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import PageHero from '../../../components/ui/PageHero';
import api from '../../../lib/api';

export default function CoinDetailPage() {
    const { symbol } = useParams();
    const [coin, setCoin] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            const response = await api.get(`/coins/${symbol}`);
            setCoin(response.data.data);
            setLoading(false);
        };

        void load();
    }, [symbol]);

    if (loading) return <LoadingState text="Loading coin detail..." />;
    if (!coin) return <ErrorState text="Coin not found." />;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow="Coin Detail"
                title={coin.symbol}
                text="Các chỉ số quan trọng được đưa ra thành card để nhìn nhanh thay vì phải đọc từ bảng dài."
            >
                <span className="app-chip">Price change {coin.priceChangePercent ?? '-'}%</span>
                <span className="app-chip">Volume {coin.quoteVolume ?? '-'}</span>
            </PageHero>

            <section className="app-detail-grid">
                {[
                    ['Symbol', coin.symbol],
                    ['Last Price', coin.lastPrice],
                    ['High', coin.highPrice],
                    ['Low', coin.lowPrice],
                    ['Open', coin.openPrice],
                    ['Change', `${coin.priceChangePercent}%`],
                ].map(([label, value]) => (
                    <article className="app-detail-card" key={label}>
                        <p className="app-detail-card__label">{label}</p>
                        <p className="app-detail-card__value">{value}</p>
                    </article>
                ))}
            </section>
        </div>
    );
}
