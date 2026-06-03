import { useMemo } from 'react';
import { useLanguage } from '../../i18n/context/LanguageContext';
import '../../../../../scss/modules/_workspace-dashboard.scss';
import {
    WorkspaceDashboardContent,
    WorkspaceDashboardHero,
    WorkspaceDashboardStats,
} from '../workspace-dashboard/WorkspaceDashboardSections';
import { buildWorkspaceDashboardData } from '../workspace-dashboard/workspaceDashboard.data';

/**
 * Render the workspace dashboard inside the shared site shell.
 *
 * @returns {import('react').JSX.Element}
 */
export default function DashboardPage() {
    const { t } = useLanguage();
    const dashboard = useMemo(() => buildWorkspaceDashboardData(t), [t]);

    return (
        <div className="workspace-dashboard">
            <WorkspaceDashboardHero
                eyebrow={t('dashboard.hero.eyebrow')}
                title={t('dashboard.hero.title')}
                text={t('dashboard.hero.text')}
                primaryLabel={t('dashboard.hero.primaryCta')}
                secondaryLabel={t('dashboard.hero.secondaryCta')}
                highlights={dashboard.highlights}
            />
            <WorkspaceDashboardStats items={dashboard.stats} />
            <WorkspaceDashboardContent
                modulesTitle={t('dashboard.modules.title')}
                openLabel={t('dashboard.modules.openLabel')}
                viewAllLabel={t('dashboard.activity.viewAll')}
                activityTitle={t('dashboard.activity.title')}
                modules={dashboard.modules}
                activity={dashboard.activity}
            />
        </div>
    );
}
