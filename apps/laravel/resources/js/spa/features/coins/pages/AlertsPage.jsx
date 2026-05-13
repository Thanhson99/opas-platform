import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import EmptyState from '../../../components/ui/EmptyState';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import MetricCard from '../../../components/ui/MetricCard';
import PageHero from '../../../components/ui/PageHero';
import { useAuth } from '../../auth/context/AuthContext';
import api from '../../../lib/api';

export default function AlertsPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { isAuthenticated } = useAuth();
    const [alerts, setAlerts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const load = async () => {
        setLoading(true);
        try {
            const response = await api.get('/coins/alerts');
            setAlerts(response.data.data ?? []);
            setError('');
        } catch {
            setAlerts([]);
            setError('Không tải được danh sách price alerts.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void load();
    }, []);

    const toggle = async (id) => {
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        try {
            await api.patch(`/coins/alerts/${id}/toggle`);
            await load();
        } catch {
            setError('Không cập nhật được trạng thái alert.');
        }
    };

    if (loading) return <LoadingState text="Loading alerts..." />;
    if (error) return <ErrorState text={error} />;

    const activeCount = alerts.filter((alert) => alert.is_active).length;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow="Price Alerts"
                title="Rule cảnh báo nên rõ trạng thái và thao tác nhanh."
                text="Màn này ưu tiên xem nhanh rule nào đang bật, threshold nào cần sửa và chuyển sang trang edit mà không bị rối."
            >
                <span className="app-chip">PATCH toggle status</span>
                <span className="app-chip">Edit theo từng rule</span>
            </PageHero>

            <section className="app-metrics-grid">
                <MetricCard
                    label="Alerts"
                    value={alerts.length}
                    hint="Tổng số rule đang cấu hình"
                    tone="sky"
                />
                <MetricCard
                    label="Active"
                    value={activeCount}
                    hint="Rule đang bật để nhận cảnh báo"
                    tone="mint"
                />
            </section>

            <section className="app-surface">
                <div className="app-surface__header">
                    <div>
                        <h2 className="app-surface__title">Alert settings</h2>
                        <p className="app-surface__text">
                            Mỗi rule giữ threshold, kiểu cảnh báo, chiều biến động và trạng thái.
                        </p>
                    </div>
                </div>
                {alerts.length === 0 ? (
                    <EmptyState text="Chưa có rule cảnh báo nào được cấu hình." />
                ) : null}
                <div className="app-card-stack">
                    {alerts.map((alert) => (
                        <article className="app-list-card" key={alert.id}>
                            <div className="app-list-card__head">
                                <div>
                                    <strong className="app-list-card__title">
                                        {alert.threshold_percent ?? 'Custom'}%
                                    </strong>
                                    <div className="app-chip-row">
                                        <span className="app-chip">{alert.type}</span>
                                        <span className="app-chip">
                                            {alert.direction ?? 'none'}
                                        </span>
                                        <span
                                            className={`app-status-pill ${
                                                alert.is_active
                                                    ? 'app-status-pill--success'
                                                    : 'app-status-pill--muted'
                                            }`}
                                        >
                                            {alert.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                </div>
                                <div className="app-action-row">
                                    <Link
                                        className="app-button app-button--primary"
                                        to={
                                            isAuthenticated
                                                ? `/coins/price-alert-settings/${alert.id}/edit`
                                                : '/login'
                                        }
                                        state={!isAuthenticated ? { from: location } : undefined}
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        className="app-button app-button--ghost"
                                        onClick={() => toggle(alert.id)}
                                    >
                                        Toggle
                                    </button>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            </section>
        </div>
    );
}
