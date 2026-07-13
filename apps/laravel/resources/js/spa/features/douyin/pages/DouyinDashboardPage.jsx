import Button from '../../../components/atoms/Button';
import LoadingState from '../../../components/ui/LoadingState';
import { useDouyinDashboard } from '../hooks/useDouyinDashboard';

const tabs = [
    { key: 'preview', label: 'Preview' },
    { key: 'downloaded', label: 'Downloaded' },
    { key: 'processing', label: 'Processing' },
    { key: 'posted', label: 'Posted' },
    { key: 'failed', label: 'Failed' },
];

/**
 * Render the Douyin keyword-to-download dashboard.
 */
export default function DouyinDashboardPage() {
    const dashboard = useDouyinDashboard();

    if (dashboard.loading) {
        return <LoadingState text="Loading Douyin dashboard..." />;
    }

    return (
        <div className="app-shell douyin-dashboard">
            <section className="app-hero douyin-hero">
                <div className="app-hero__main">
                    <p className="app-hero__eyebrow">Douyin Workflow</p>
                    <h1 className="app-hero__title">Keyword preview to local video processing.</h1>
                    <p className="app-hero__text">
                        Crawl keyword result cards, reject weak previews, download selected videos,
                        then move finished items to posted or delete/archive them.
                    </p>
                    <div className="app-inline-stats">
                        <span className="app-chip">{dashboard.metrics.preview} preview</span>
                        <span className="app-chip">{dashboard.metrics.selected} selected</span>
                        <span className="app-chip">{dashboard.metrics.downloaded} downloaded</span>
                    </div>
                </div>
                <div className="app-hero-card">
                    <p className="app-hero-card__eyebrow">Active job</p>
                    <h2 className="app-hero-card__title">
                        {dashboard.currentJob?.keyword ?? 'No crawl yet'}
                    </h2>
                    <p className="app-hero-card__text">
                        {dashboard.currentJob
                            ? `${dashboard.currentJob.total_found ?? 0} found, ${dashboard.currentJob.total_selected ?? 0} selected`
                            : 'Choose a keyword and crawl preview cards first.'}
                    </p>
                </div>
            </section>

            {dashboard.error ? <div className="douyin-alert">{dashboard.error}</div> : null}

            <section className="app-surface douyin-controls">
                <div>
                    <p className="app-surface__title">Hot Keywords</p>
                    <p className="app-surface__text">
                        Presets come from the database. Add a manual keyword when the trend shifts.
                    </p>
                </div>

                <div className="douyin-keyword-grid">
                    {dashboard.keywords.map((keyword) => (
                        <button
                            className={`douyin-chip ${dashboard.form.keyword === keyword.name ? 'is-active' : ''}`}
                            key={keyword.id}
                            onClick={() => dashboard.selectKeyword(String(keyword.name))}
                            type="button"
                        >
                            {keyword.name}
                        </button>
                    ))}
                </div>

                <div className="douyin-form-grid">
                    <label className="douyin-field">
                        <span>Keyword</span>
                        <input
                            onChange={(event) =>
                                dashboard.setForm((value) => ({
                                    ...value,
                                    keyword: event.target.value,
                                }))
                            }
                            value={dashboard.form.keyword}
                        />
                    </label>
                    <label className="douyin-field">
                        <span>Limit</span>
                        <input
                            min="1"
                            onChange={(event) =>
                                dashboard.setForm((value) => ({
                                    ...value,
                                    limit: Number(event.target.value),
                                }))
                            }
                            type="number"
                            value={dashboard.form.limit}
                        />
                    </label>
                    <Button
                        loading={dashboard.actionLoading === 'crawl'}
                        onClick={dashboard.crawlPreview}
                    >
                        Crawl Preview
                    </Button>
                </div>

                <div className="douyin-form-grid">
                    <label className="douyin-field">
                        <span>New keyword</span>
                        <input
                            onChange={(event) =>
                                dashboard.setForm((value) => ({
                                    ...value,
                                    newKeyword: event.target.value,
                                }))
                            }
                            value={dashboard.form.newKeyword}
                        />
                    </label>
                    <label className="douyin-field">
                        <span>Category</span>
                        <input
                            onChange={(event) =>
                                dashboard.setForm((value) => ({
                                    ...value,
                                    category: event.target.value,
                                }))
                            }
                            value={dashboard.form.category}
                        />
                    </label>
                    <Button
                        loading={dashboard.actionLoading === 'keyword'}
                        onClick={dashboard.addKeyword}
                        variant="secondary"
                    >
                        Add Keyword
                    </Button>
                </div>
            </section>

            <section className="app-surface douyin-preview">
                <div className="douyin-section-header">
                    <div>
                        <p className="app-surface__title">Preview Results</p>
                        <p className="app-surface__text">
                            Remove videos you do not want before running the download step.
                        </p>
                    </div>
                    <div className="douyin-actions">
                        <Button
                            loading={dashboard.actionLoading === 'select-all'}
                            onClick={() => dashboard.selectAllPreview(true)}
                            size="sm"
                            variant="secondary"
                        >
                            Select all
                        </Button>
                        <Button
                            loading={dashboard.actionLoading === 'unselect-all'}
                            onClick={() => dashboard.selectAllPreview(false)}
                            size="sm"
                            variant="ghost"
                        >
                            Unselect all
                        </Button>
                        <Button
                            disabled={!dashboard.currentJob?.id || dashboard.metrics.selected === 0}
                            loading={dashboard.actionLoading === 'process'}
                            onClick={dashboard.processSelected}
                        >
                            Process selected
                        </Button>
                    </div>
                </div>

                <div className="douyin-card-grid">
                    {dashboard.previewVideos.map((video) => (
                        <PreviewCard
                            key={video.id}
                            onDelete={dashboard.deleteVideo}
                            onToggle={dashboard.toggleVideoSelection}
                            video={video}
                        />
                    ))}
                    {dashboard.previewVideos.length === 0 ? (
                        <div className="douyin-empty">No preview cards yet.</div>
                    ) : null}
                </div>
            </section>

            <section className="app-surface douyin-library">
                <div className="douyin-tabs">
                    {tabs.map((tab) => (
                        <button
                            className={`douyin-tab ${dashboard.activeStatus === tab.key ? 'is-active' : ''}`}
                            key={tab.key}
                            onClick={() => dashboard.setActiveStatus(tab.key)}
                            type="button"
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
                <VideoTable
                    onDelete={dashboard.deleteVideo}
                    onMarkPosted={dashboard.markPosted}
                    videos={dashboard.listedVideos}
                />
            </section>
        </div>
    );
}

/**
 * Render one preview card with selection controls.
 *
 * @param {{
 *   video: Record<string, unknown>,
 *   onToggle: (video: Record<string, unknown>, selected: boolean) => Promise<void>,
 *   onDelete: (video: Record<string, unknown>) => Promise<void>,
 * }} props
 */
function PreviewCard({ video, onToggle, onDelete }) {
    const title = video.title || `Video ${video.video_id}`;

    return (
        <article className={`douyin-card ${video.selected ? 'is-selected' : 'is-rejected'}`}>
            <div className="douyin-card__media">
                {video.cover_url ? (
                    <img alt="" src={video.cover_url} />
                ) : (
                    <span>{video.video_id}</span>
                )}
            </div>
            <div className="douyin-card__body">
                <h2>{title}</h2>
                <p>{video.author || 'Unknown author'}</p>
                <a href={video.source_url} rel="noreferrer" target="_blank">
                    Open source
                </a>
            </div>
            <div className="douyin-card__actions">
                <label className="douyin-checkbox">
                    <input
                        checked={Boolean(video.selected)}
                        onChange={(event) => onToggle(video, event.target.checked)}
                        type="checkbox"
                    />
                    <span>Selected</span>
                </label>
                <Button
                    onClick={() => onToggle(video, !video.selected)}
                    size="sm"
                    variant="secondary"
                >
                    {video.selected ? 'Reject' : 'Restore'}
                </Button>
                <Button onClick={() => onDelete(video)} size="sm" variant="danger">
                    Delete
                </Button>
            </div>
        </article>
    );
}

/**
 * Render videos by workflow status.
 *
 * @param {{
 *   videos: Array<Record<string, unknown>>,
 *   onMarkPosted: (video: Record<string, unknown>, deleteAfterPosted?: boolean) => Promise<void>,
 *   onDelete: (video: Record<string, unknown>) => Promise<void>,
 * }} props
 */
function VideoTable({ videos, onMarkPosted, onDelete }) {
    if (videos.length === 0) {
        return <div className="douyin-empty">No videos in this tab.</div>;
    }

    return (
        <div className="douyin-table-wrap">
            <table className="douyin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Keyword</th>
                        <th>Status</th>
                        <th>Local path</th>
                        <th>Downloaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {videos.map((video) => (
                        <tr key={video.id}>
                            <td>{video.title || video.video_id}</td>
                            <td>{video.keyword}</td>
                            <td>
                                <span className={`douyin-status douyin-status--${video.status}`}>
                                    {video.status}
                                </span>
                            </td>
                            <td>{video.local_path || '-'}</td>
                            <td>{video.downloaded_at || '-'}</td>
                            <td>
                                <div className="douyin-table-actions">
                                    <Button
                                        disabled={video.status !== 'downloaded'}
                                        onClick={() => onMarkPosted(video)}
                                        size="sm"
                                        variant="secondary"
                                    >
                                        Mark posted
                                    </Button>
                                    <Button
                                        onClick={() => onDelete(video)}
                                        size="sm"
                                        variant="danger"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
