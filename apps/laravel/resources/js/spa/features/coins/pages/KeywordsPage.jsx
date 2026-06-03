import { useLocation, useNavigate } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import PageHero from '../../../components/ui/PageHero';
import { useAuth } from '../../auth/context/AuthContext';
import { useLanguage } from '../../i18n/context/LanguageContext';
import KeywordFormCard from '../components/KeywordFormCard';
import KeywordListCard from '../components/KeywordListCard';
import KeywordMetrics from '../components/KeywordMetrics';
import { useCoinKeywords } from '../hooks/useCoinKeywords';

/**
 * Render keyword and tag management for coin-content automation inputs.
 */
export default function KeywordsPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { isAuthenticated } = useAuth();
    const { t } = useLanguage();
    const { keywords, form, metrics, loading, error, setForm, createKeyword, deleteKeyword } =
        useCoinKeywords({
            loadErrorText: t('keywordsPage.loadError'),
            createErrorText: t('keywordsPage.createError'),
            deleteErrorText: t('keywordsPage.deleteError'),
        });

    const submitKeyword = async (event) => {
        event.preventDefault();

        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        await createKeyword();
    };

    const removeKeyword = async (id) => {
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        await deleteKeyword(id);
    };

    if (loading) return <LoadingState text={t('keywordsPage.loading')} />;
    if (error) return <ErrorState text={error} />;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow={t('keywordsPage.hero.eyebrow')}
                title={t('keywordsPage.hero.title')}
                text={t('keywordsPage.hero.text')}
            >
                <span className="app-chip">{t('keywordsPage.hero.keywordChip')}</span>
                <span className="app-chip">{t('keywordsPage.hero.apiChip')}</span>
            </PageHero>

            <KeywordMetrics metrics={metrics} t={t} />

            <div className="app-form-grid">
                <KeywordFormCard
                    form={form}
                    t={t}
                    onFormChange={setForm}
                    onSubmit={submitKeyword}
                />
                <KeywordListCard keywords={keywords} t={t} onDelete={removeKeyword} />
            </div>
        </div>
    );
}
