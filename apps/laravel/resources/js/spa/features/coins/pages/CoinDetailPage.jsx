import { useParams } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import PageHero from '../../../components/ui/PageHero';
import { useLanguage } from '../../i18n/context/LanguageContext';
import CoinDetailGrid from '../components/CoinDetailGrid';
import { useCoinDetail } from '../hooks/useCoinDetail';

/**
 * Render the detail view for one monitored coin symbol.
 */
export default function CoinDetailPage() {
    const { symbol } = useParams();
    const { t } = useLanguage();
    const { coin, detailRows, loading, error } = useCoinDetail({
        symbol,
        loadErrorText: t('coinDetailPage.notFound'),
        t,
    });

    if (loading) return <LoadingState text={t('coinDetailPage.loading')} />;
    if (error || !coin) return <ErrorState text={error || t('coinDetailPage.notFound')} />;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow={t('coinDetailPage.hero.eyebrow')}
                title={coin.symbol}
                text={t('coinDetailPage.hero.text')}
            >
                <span className="app-chip">
                    {t('coinDetailPage.hero.priceChange')} {coin.priceChangePercent ?? '-'}%
                </span>
                <span className="app-chip">
                    {t('coinDetailPage.hero.volume')} {coin.quoteVolume ?? '-'}
                </span>
            </PageHero>

            <CoinDetailGrid rows={detailRows} />
        </div>
    );
}
