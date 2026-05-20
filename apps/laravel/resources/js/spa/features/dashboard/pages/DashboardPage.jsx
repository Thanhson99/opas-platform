import { Link } from 'react-router-dom';
import { useLanguage } from '../../i18n/context/LanguageContext';

/**
 * Render the workspace landing page with navigation into the main tools.
 */
export default function DashboardPage() {
    const { t } = useLanguage();

    const quickLinks = [
        {
            title: t('nav.coins'),
            text: t('dashboard.coinsText'),
            href: '/coins',
        },
        {
            title: t('nav.stocks'),
            text: t('dashboard.stocksText'),
            href: '/stocks',
        },
        {
            title: t('nav.contentKeywords'),
            text: t('dashboard.keywordsText'),
            href: '/coins/feed-keywords',
        },
        {
            title: t('nav.trendingVideos'),
            text: t('dashboard.videosText'),
            href: '/video-automation/trending',
        },
    ];

    return (
        <div className="app-shell">
            <section className="app-dashboard-hero">
                <div className="app-dashboard-hero__visual">
                    <img src="/storage/images/brand/opas-banner.png" alt="OPAS banner" />
                </div>
                <div className="app-dashboard-hero__content">
                    <p className="app-dashboard-hero__eyebrow">{t('dashboard.eyebrow')}</p>
                    <h2 className="app-dashboard-hero__title">{t('dashboard.title')}</h2>
                    <p className="app-dashboard-hero__text">{t('dashboard.text')}</p>
                    <div className="app-dashboard-hero__links">
                        {quickLinks.map((item) => (
                            <Link to={item.href} className="app-dashboard-link" key={item.href}>
                                <strong>{item.title}</strong>
                                <span>{item.text}</span>
                            </Link>
                        ))}
                    </div>
                </div>
            </section>
        </div>
    );
}
