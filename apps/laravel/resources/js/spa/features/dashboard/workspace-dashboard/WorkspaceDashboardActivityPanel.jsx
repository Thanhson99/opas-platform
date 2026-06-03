import { memo } from 'react';
import AppIcon from '../../../components/icons/AppIcon';
import WorkspaceDashboardActivityItem from './WorkspaceDashboardActivityItem';

/**
 * Render recent dashboard activity.
 *
 * @param {{ title: string, viewAllLabel: string, activity: Array<{title: string, emphasis?: string, timestamp: string, icon: string, tone: string}> }} props
 * @returns {import('react').JSX.Element}
 */
function WorkspaceDashboardActivityPanel({ title, viewAllLabel, activity }) {
    return (
        <aside className="workspace-dashboard__activity">
            <div className="workspace-dashboard__activity-head">
                <h3>{title}</h3>
                <button type="button" title={viewAllLabel}>
                    <span>{viewAllLabel}</span>
                    <AppIcon name="arrow-right" />
                </button>
            </div>
            <div className="workspace-dashboard__activity-panel">
                {activity.map((item) => (
                    <WorkspaceDashboardActivityItem
                        item={item}
                        key={`${item.title}-${item.timestamp}`}
                    />
                ))}
            </div>
        </aside>
    );
}

export default memo(WorkspaceDashboardActivityPanel);
