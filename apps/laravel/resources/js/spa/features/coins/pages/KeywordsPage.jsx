import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import EmptyState from '../../../components/ui/EmptyState';
import ErrorState from '../../../components/ui/ErrorState';
import LoadingState from '../../../components/ui/LoadingState';
import MetricCard from '../../../components/ui/MetricCard';
import PageHero from '../../../components/ui/PageHero';
import { useAuth } from '../../auth/context/AuthContext';
import api from '../../../lib/api';

export default function KeywordsPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { isAuthenticated } = useAuth();
    const [keywords, setKeywords] = useState([]);
    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState({ keyword: '', tags: '' });
    const [error, setError] = useState('');

    const load = async () => {
        setLoading(true);
        try {
            const response = await api.get('/coins/keywords');
            setKeywords(response.data.data ?? []);
            setError('');
        } catch {
            setKeywords([]);
            setError('Không tải được danh sách keywords.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void load();
    }, []);

    const submit = async (event) => {
        event.preventDefault();
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        const tags = form.tags
            .split(',')
            .map((tag) => tag.trim())
            .filter(Boolean);
        try {
            await api.post('/coins/keywords', { keyword: form.keyword, tags });
            setForm({ keyword: '', tags: '' });
            await load();
        } catch {
            setError('Không tạo được keyword mới.');
        }
    };

    const removeItem = async (id) => {
        if (!isAuthenticated) {
            navigate('/login', { state: { from: location } });
            return;
        }

        try {
            await api.delete(`/coins/keywords/${id}`);
            await load();
        } catch {
            setError('Không xóa được keyword.');
        }
    };

    if (loading) return <LoadingState text="Loading keywords..." />;
    if (error) return <ErrorState text={error} />;

    return (
        <div className="app-shell">
            <PageHero
                eyebrow="Keyword Manager"
                title="Bộ từ khóa nội dung nên dễ thêm, dễ xóa và dễ nhìn theo tag."
                text="Màn này giữ thao tác rất trực tiếp: tạo keyword, gắn tag và rà nhanh danh sách để feed sang automation."
            >
                <span className="app-chip">Keyword + tags</span>
                <span className="app-chip">Quản lý qua REST API</span>
            </PageHero>

            <section className="app-metrics-grid">
                <MetricCard
                    label="Keywords"
                    value={keywords.length}
                    hint="Số keyword đang có trong hệ thống"
                    tone="sky"
                />
                <MetricCard
                    label="Tagged entries"
                    value={keywords.filter((item) => item.tags.length > 0).length}
                    hint="Keyword đã được gắn tag để phân loại"
                    tone="mint"
                />
            </section>

            <div className="app-form-grid">
                <section className="app-form-card app-form-card--accent">
                    <h2 className="app-form-card__title">Create keyword</h2>
                    <p className="app-form-card__text">
                        Tách tag bằng dấu phẩy để tạo nhanh các nhóm chủ đề.
                    </p>
                    <form className="app-form" onSubmit={submit}>
                        <div className="app-field">
                            <label className="app-label">Keyword</label>
                            <input
                                className="app-input"
                                value={form.keyword}
                                onChange={(event) =>
                                    setForm((value) => ({
                                        ...value,
                                        keyword: event.target.value,
                                    }))
                                }
                                required
                            />
                        </div>
                        <div className="app-field">
                            <label className="app-label">Tags</label>
                            <input
                                className="app-input"
                                placeholder="tag1, tag2"
                                value={form.tags}
                                onChange={(event) =>
                                    setForm((value) => ({
                                        ...value,
                                        tags: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        <button className="app-button app-button--primary" type="submit">
                            Create keyword
                        </button>
                    </form>
                </section>

                <section className="app-form-card">
                    <h2 className="app-form-card__title">Keyword list</h2>
                    <p className="app-form-card__text">
                        Mỗi item là một nhóm thao tác nhỏ, không cần đi qua bảng dài.
                    </p>
                    {keywords.length === 0 ? (
                        <EmptyState text="Chưa có keyword nào trong hệ thống." />
                    ) : null}
                    <div className="app-card-stack">
                        {keywords.map((item) => (
                            <div className="app-list-card" key={item.id}>
                                <div className="app-list-card__head">
                                    <div>
                                        <strong className="app-list-card__title">
                                            {item.keyword}
                                        </strong>
                                        <div className="app-chip-row">
                                            {item.tags.map((tag) => (
                                                <span className="app-chip" key={tag.id}>
                                                    #{tag.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        className="app-button app-button--danger"
                                        onClick={() => removeItem(item.id)}
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </div>
    );
}
