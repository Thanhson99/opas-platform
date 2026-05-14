import { useEffect, useState } from 'react';
import EmptyState from '../../../components/ui/EmptyState';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import MetricCard from '../../../components/ui/MetricCard';
import PageHero from '../../../components/ui/PageHero';
import api from '../../../lib/api';

export default function VideosPage() {
    const [groups, setGroups] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            try {
                const response = await api.get('/videos/trending');
                setGroups(response.data.data ?? []);
                setError('');
            } catch {
                setGroups([]);
                setError('Không tải được dữ liệu trending videos.');
            } finally {
                setLoading(false);
            }
        };
        void load();
    }, []);

    if (loading) return <LoadingState text="Loading videos..." />;
    if (error) return <ErrorState text={error} />;

    const totalVideos = groups.reduce((count, group) => count + group.links.length, 0);

    return (
        <div className="app-shell">
            <PageHero
                eyebrow="Video Automation"
                title="Nguồn video trending được gom thành từng cụm để xử lý tiếp."
                text="Mỗi group đại diện cho một keyword. Giao diện ưu tiên preview nhanh và mở source trực tiếp khi cần kiểm tra."
            >
                <span className="app-chip">Grouped by keyword</span>
                <span className="app-chip">Open source trực tiếp</span>
            </PageHero>

            <section className="app-metrics-grid">
                <MetricCard
                    label="Keyword groups"
                    value={groups.length}
                    hint="Số cụm video theo keyword"
                    tone="sky"
                />
                <MetricCard
                    label="Video sources"
                    value={totalVideos}
                    hint="Tổng số nguồn hiện đang có"
                    tone="amber"
                />
            </section>

            {groups.length === 0 ? (
                <EmptyState text="Chưa có video source nào để hiển thị." />
            ) : null}

            <div className="video-grid">
                {groups.map((group) => (
                    <article className="video-panel" key={group.keyword}>
                        <div className="video-panel__head">
                            <div>
                                <h3 className="video-panel__title">{group.keyword}</h3>
                                <p className="video-panel__count">
                                    {group.links.length} video source
                                </p>
                            </div>
                        </div>
                        <div className="video-panel__media">
                            {group.links.map((link) => (
                                <div className="video-card" key={link}>
                                    <video controls preload="metadata" playsInline>
                                        <source src={link} type="video/mp4" />
                                    </video>
                                    <a
                                        href={link}
                                        className="app-inline-link"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Open source
                                    </a>
                                </div>
                            ))}
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}
