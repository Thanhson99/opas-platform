import { useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import PageHero from '../../../components/ui/PageHero';
import { useAuth } from '../../auth/context/AuthContext';
import { useLanguage } from '../../i18n/context/LanguageContext';
import AlertEditFormCard from '../components/AlertEditFormCard';
import { useCoinAlertEditor } from '../hooks/useCoinAlertEditor';

/**
 * Render the edit screen for one coin price-alert rule.
 */
export default function AlertEditPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { isAuthenticated, loading: authLoading } = useAuth();
    const { t } = useLanguage();
    const { form, loading, error, setForm, saveAlert } = useCoinAlertEditor({
        id,
        enabled: !authLoading && isAuthenticated,
        loadErrorText: t('alertEditPage.loadError'),
        saveErrorText: t('alertEditPage.saveError'),
    });

    useEffect(() => {
        if (!authLoading && !isAuthenticated) {
            navigate('/login', {
                replace: true,
                state: { from: { pathname: `/coins/price-alert-settings/${id}/edit` } },
            });
        }
    }, [authLoading, id, isAuthenticated, navigate]);

    const submit = async (event) => {
        event.preventDefault();

        if (await saveAlert()) {
            navigate('/coins/price-alert-settings');
        }
    };

    if (error && !form) return <ErrorState text={error} />;
    if (loading || !form) return <LoadingState text={t('alertEditPage.loading')} />;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow={t('alertEditPage.hero.eyebrow')}
                title={t('alertEditPage.hero.title')}
                text={t('alertEditPage.hero.text')}
            />

            {error ? <ErrorState text={error} /> : null}

            <AlertEditFormCard
                form={form}
                t={t}
                onBack={() => navigate('/coins/price-alert-settings')}
                onFormChange={setForm}
                onSubmit={submit}
            />
        </div>
    );
}
