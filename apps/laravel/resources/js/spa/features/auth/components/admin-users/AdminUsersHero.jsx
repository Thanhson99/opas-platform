import PageHero from '../../../../components/ui/PageHero';

/**
 * Render admin user-management hero metrics.
 *
 * @param {{ summary: { total: number, page: number, visible: number }, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function AdminUsersHero({ summary, t }) {
    return (
        <PageHero
            eyebrow={t('adminUsers.hero.eyebrow')}
            title={t('adminUsers.hero.title')}
            text={t('adminUsers.hero.text')}
        >
            <span className="app-chip">
                {t('adminUsers.summary.total')} {summary.total}
            </span>
            <span className="app-chip">
                {t('adminUsers.summary.page')} {summary.page}
            </span>
            <span className="app-chip">
                {t('adminUsers.summary.visible')} {summary.visible}
            </span>
        </PageHero>
    );
}
