import AppIcon from '../../../components/icons/AppIcon';
import WorkspaceDashboardFlowMap from './WorkspaceDashboardFlowMap';

/**
 * Render the dashboard hero callout.
 *
 * @param {{ eyebrow: string, title: string, text: string, primaryLabel: string, secondaryLabel: string }} props
 * @returns {import('react').JSX.Element}
 */
export default function WorkspaceDashboardHeroCard({
    eyebrow,
    title,
    text,
    primaryLabel,
    secondaryLabel,
}) {
    return (
        <article className="workspace-dashboard__hero-card">
            <div className="workspace-dashboard__hero-status" aria-hidden="true">
                <span>OPAS // ONLINE</span>
                <span>FLOW SECURE</span>
                <span>NODE 07</span>
            </div>
            <div className="workspace-dashboard__hero-copy">
                <span className="workspace-dashboard__hero-eyebrow">{eyebrow}</span>
                <h2>{title}</h2>
                <p>{text}</p>
                <div className="workspace-dashboard__hero-actions">
                    <button
                        type="button"
                        className="app-button app-button--primary"
                        title={primaryLabel}
                    >
                        <AppIcon name="activity" />
                        {primaryLabel}
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        title={secondaryLabel}
                    >
                        <AppIcon name="terminal" />
                        {secondaryLabel}
                    </button>
                </div>
            </div>
            <div className="workspace-dashboard__hero-visual">
                <WorkspaceDashboardFlowMap />
                <div className="workspace-dashboard__hero-signs" aria-hidden="true">
                    <span className="workspace-dashboard__neon-sign workspace-dashboard__neon-sign--cyan">
                        n8n flow
                    </span>
                    <span className="workspace-dashboard__neon-sign workspace-dashboard__neon-sign--pink">
                        ai signal
                    </span>
                </div>
                <div className="workspace-dashboard__hero-core">
                    <span className="workspace-dashboard__hero-ring" />
                    <span className="workspace-dashboard__hero-pulse">
                        <AppIcon name="activity" />
                    </span>
                </div>
            </div>
        </article>
    );
}
