import { memo } from 'react';
import AppIcon from '../../../components/icons/AppIcon';

/**
 * Render the dashboard highlight rail.
 *
 * @param {{ items: Array<{title: string, text: string, icon: string, tone: string}> }} props
 * @returns {import('react').JSX.Element}
 */
function WorkspaceDashboardHighlightPanel({ items }) {
    return (
        <aside className="workspace-dashboard__highlight-panel">
            {items.map((item) => (
                <article className="workspace-dashboard__highlight-card" key={item.title}>
                    <span
                        className={`workspace-dashboard__highlight-icon workspace-dashboard__highlight-icon--${item.tone}`}
                    >
                        <AppIcon name={item.icon} />
                    </span>
                    <div>
                        <h4>{item.title}</h4>
                        <p>{item.text}</p>
                    </div>
                </article>
            ))}
        </aside>
    );
}

export default memo(WorkspaceDashboardHighlightPanel);
