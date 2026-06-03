import { memo } from 'react';
import AppIcon from '../../../components/icons/AppIcon';

/**
 * Render one dashboard activity row.
 *
 * @param {{ item: {title: string, emphasis?: string, timestamp: string, icon: string, tone: string} }} props
 * @returns {import('react').JSX.Element}
 */
function WorkspaceDashboardActivityItem({ item }) {
    return (
        <article className="workspace-dashboard__activity-item">
            <span
                className={`workspace-dashboard__activity-icon workspace-dashboard__activity-icon--${item.tone}`}
            >
                <AppIcon name={item.icon} />
            </span>
            <div className="workspace-dashboard__activity-copy">
                <p>
                    {item.title} {item.emphasis ? <strong>{item.emphasis}</strong> : null}
                </p>
                <span className="workspace-dashboard__activity-time">{item.timestamp}</span>
            </div>
        </article>
    );
}

export default memo(WorkspaceDashboardActivityItem);
