import EmptyState from '../../../components/ui/EmptyState';

/**
 * Render grouped trending video sources.
 *
 * @param {{ groups: Array<Record<string, unknown>>, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function VideoGroupGrid({ groups, t }) {
    if (groups.length === 0) {
        return <EmptyState text={t('videosPage.empty')} />;
    }

    return (
        <div className="video-grid">
            {groups.map((group) => (
                <VideoGroupPanel group={group} key={group.keyword} t={t} />
            ))}
        </div>
    );
}

/**
 * Render one keyword group of video sources.
 *
 * @param {{ group: Record<string, unknown>, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
function VideoGroupPanel({ group, t }) {
    const links = group.links ?? [];

    return (
        <article className="video-panel">
            <div className="video-panel__head">
                <div>
                    <h3 className="video-panel__title">{group.keyword}</h3>
                    <p className="video-panel__count">
                        {links.length} {t('videosPage.sourceCount')}
                    </p>
                </div>
            </div>
            <div className="video-panel__media">
                {links.map((link) => (
                    <VideoSourceCard key={link} link={link} t={t} />
                ))}
            </div>
        </article>
    );
}

/**
 * Render one video preview and source link.
 *
 * @param {{ link: string, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
function VideoSourceCard({ link, t }) {
    return (
        <div className="video-card">
            <video controls preload="metadata" playsInline>
                <source src={link} type="video/mp4" />
            </video>
            <a href={link} className="app-inline-link" target="_blank" rel="noreferrer">
                {t('videosPage.openSource')}
            </a>
        </div>
    );
}
