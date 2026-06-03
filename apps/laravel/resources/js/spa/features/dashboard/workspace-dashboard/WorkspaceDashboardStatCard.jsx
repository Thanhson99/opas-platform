import { memo } from 'react';
import AppIcon from '../../../components/icons/AppIcon';

/**
 * Render one dashboard metric card.
 *
 * @param {{ item: {label: string, note?: string, value: string, delta: string, deltaText: string, icon: string, tone: string, deltaIcon?: string} }} props
 * @returns {import('react').JSX.Element}
 */
function WorkspaceDashboardStatCard({ item }) {
    return (
        <article className="workspace-dashboard__stat-card">
            <div className="workspace-dashboard__stat-main">
                <span
                    className={`workspace-dashboard__stat-icon workspace-dashboard__stat-icon--${item.tone}`}
                >
                    <AppIcon name={item.icon} />
                </span>
                <div>
                    <p className="workspace-dashboard__stat-label">
                        {item.label}
                        {item.note ? <span>{item.note}</span> : null}
                    </p>
                    <h3>{item.value}</h3>
                </div>
            </div>
            <p className="workspace-dashboard__stat-delta">
                <AppIcon name={item.deltaIcon ?? 'trend-up'} />
                <strong>{item.delta}</strong>
                <span>{item.deltaText}</span>
            </p>
        </article>
    );
}

export default memo(WorkspaceDashboardStatCard);
