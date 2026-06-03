import WorkspaceDashboardModuleCard from './WorkspaceDashboardModuleCard';

/**
 * Render dashboard module shortcuts.
 *
 * @param {{ title: string, openLabel: string, modules: Array<{title: string, text: string, href: string, icon: string, tone: string}> }} props
 * @returns {import('react').JSX.Element}
 */
export default function WorkspaceDashboardModuleGrid({ title, openLabel, modules }) {
    return (
        <div className="workspace-dashboard__modules">
            <h3>{title}</h3>
            <div className="workspace-dashboard__module-grid">
                {modules.map((item) => (
                    <WorkspaceDashboardModuleCard
                        item={item}
                        openLabel={openLabel}
                        key={item.href}
                    />
                ))}
            </div>
        </div>
    );
}
