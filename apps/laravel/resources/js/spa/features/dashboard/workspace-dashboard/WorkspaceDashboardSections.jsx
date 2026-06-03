import WorkspaceDashboardActivityPanel from './WorkspaceDashboardActivityPanel';
import WorkspaceDashboardHeroCard from './WorkspaceDashboardHeroCard';
import WorkspaceDashboardHighlightPanel from './WorkspaceDashboardHighlightPanel';
import WorkspaceDashboardModuleGrid from './WorkspaceDashboardModuleGrid';
import WorkspaceDashboardStatCard from './WorkspaceDashboardStatCard';

/**
 * Render the stat cards from the dashboard HTML reference.
 *
 * @param {{ items: Array<{label: string, note?: string, value: string, delta: string, deltaText: string, icon: string, tone: string, deltaIcon?: string}> }} props
 * @returns {import('react').JSX.Element}
 */
export function WorkspaceDashboardStats({ items }) {
    return (
        <section className="workspace-dashboard__stats">
            {items.map((item) => (
                <WorkspaceDashboardStatCard item={item} key={item.label} />
            ))}
        </section>
    );
}

/**
 * Render the hero banner and the supporting highlight cards.
 *
 * @param {{
 *   eyebrow: string,
 *   title: string,
 *   text: string,
 *   primaryLabel: string,
 *   secondaryLabel: string,
 *   highlights: Array<{title: string, text: string, icon: string, tone: string}>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function WorkspaceDashboardHero({
    eyebrow,
    title,
    text,
    primaryLabel,
    secondaryLabel,
    highlights,
}) {
    return (
        <section className="workspace-dashboard__hero-grid">
            <WorkspaceDashboardHeroCard
                eyebrow={eyebrow}
                title={title}
                text={text}
                primaryLabel={primaryLabel}
                secondaryLabel={secondaryLabel}
            />
            <WorkspaceDashboardHighlightPanel items={highlights} />
        </section>
    );
}

/**
 * Render the module cards and recent activity section.
 *
 * @param {{
 *   modulesTitle: string,
 *   openLabel: string,
 *   viewAllLabel: string,
 *   activityTitle: string,
 *   modules: Array<{title: string, text: string, href: string, icon: string, tone: string}>,
 *   activity: Array<{title: string, emphasis?: string, timestamp: string, icon: string, tone: string}>,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export function WorkspaceDashboardContent({
    modulesTitle,
    openLabel,
    viewAllLabel,
    activityTitle,
    modules,
    activity,
}) {
    return (
        <section className="workspace-dashboard__content-grid">
            <WorkspaceDashboardModuleGrid
                title={modulesTitle}
                openLabel={openLabel}
                modules={modules}
            />
            <WorkspaceDashboardActivityPanel
                title={activityTitle}
                viewAllLabel={viewAllLabel}
                activity={activity}
            />
        </section>
    );
}
