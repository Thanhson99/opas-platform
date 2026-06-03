import { memo } from 'react';
import { Link } from 'react-router-dom';
import AppIcon from '../../../components/icons/AppIcon';

/**
 * Render one dashboard module shortcut card.
 *
 * @param {{ item: {title: string, text: string, href: string, icon: string, tone: string}, openLabel: string }} props
 * @returns {import('react').JSX.Element}
 */
function WorkspaceDashboardModuleCard({ item, openLabel }) {
    return (
        <article className="workspace-dashboard__module-card">
            <span
                className={`workspace-dashboard__module-icon workspace-dashboard__module-icon--${item.tone}`}
            >
                <AppIcon name={item.icon} />
            </span>
            <div className="workspace-dashboard__module-copy">
                <h4>{item.title}</h4>
                <p>{item.text}</p>
            </div>
            <Link
                to={item.href}
                className={`workspace-dashboard__module-link workspace-dashboard__module-link--${item.tone}`}
                aria-label={`${openLabel} ${item.title}`}
            >
                <span>{openLabel}</span>
                <AppIcon name="arrow-right" />
            </Link>
        </article>
    );
}

export default memo(WorkspaceDashboardModuleCard);
