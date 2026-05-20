import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import LoadingState from '../../../components/ui/LoadingState';
import PageHero from '../../../components/ui/PageHero';
import { useAuth } from '../../auth/context/AuthContext';
import api from '../../../lib/api';

/**
 * Render the edit screen for one coin price-alert rule.
 */
export default function AlertEditPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { isAuthenticated, loading: authLoading } = useAuth();
    const [form, setForm] = useState(null);

    useEffect(() => {
        if (!authLoading && !isAuthenticated) {
            navigate('/login', {
                replace: true,
                state: { from: { pathname: `/coins/price-alert-settings/${id}/edit` } },
            });
            return;
        }

        const load = async () => {
            const response = await api.get(`/coins/alerts/${id}`);
            setForm(response.data.data);
        };
        if (isAuthenticated) {
            void load();
        }
    }, [authLoading, id, isAuthenticated, navigate]);

    if (!form) return <LoadingState text="Loading alert..." />;

    const submit = async (event) => {
        event.preventDefault();
        await api.put(`/coins/alerts/${id}`, {
            threshold_percent: form.threshold_percent,
            type: form.type,
            direction: form.direction || null,
            is_active: Boolean(form.is_active),
        });
        navigate('/coins/price-alert-settings');
    };

    return (
        <div className="app-shell">
            <PageHero
                eyebrow="Alert Editor"
                title="Sửa rule cảnh báo trong một form ngắn, rõ field."
                text="Không cần màn hình dày đặc. Chỉ giữ những trường thực sự dùng cho alert setting."
            />

            <section className="app-form-card app-form-card--accent">
                <h2 className="app-form-card__title">Edit alert</h2>
                <p className="app-form-card__text">
                    Cập nhật threshold, loại cảnh báo, chiều biến động và trạng thái hoạt động.
                </p>
                <form className="app-form" onSubmit={submit}>
                    <div className="app-field">
                        <label className="app-label">Threshold Percent</label>
                        <input
                            className="app-input"
                            type="number"
                            step="0.01"
                            value={form.threshold_percent ?? ''}
                            onChange={(event) =>
                                setForm((value) => ({
                                    ...value,
                                    threshold_percent: event.target.value,
                                }))
                            }
                        />
                    </div>
                    <div className="app-field">
                        <label className="app-label">Type</label>
                        <select
                            className="app-input"
                            value={form.type}
                            onChange={(event) =>
                                setForm((value) => ({ ...value, type: event.target.value }))
                            }
                        >
                            <option value="preset">Preset</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div className="app-field">
                        <label className="app-label">Direction</label>
                        <select
                            className="app-input"
                            value={form.direction ?? ''}
                            onChange={(event) =>
                                setForm((value) => ({
                                    ...value,
                                    direction: event.target.value || null,
                                }))
                            }
                        >
                            <option value="">None</option>
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                        </select>
                    </div>
                    <div className="app-field">
                        <label className="app-label">Status</label>
                        <select
                            className="app-input"
                            value={form.is_active ? '1' : '0'}
                            onChange={(event) =>
                                setForm((value) => ({
                                    ...value,
                                    is_active: event.target.value === '1',
                                }))
                            }
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div className="app-action-row">
                        <button className="app-button app-button--primary" type="submit">
                            Save alert
                        </button>
                        <button
                            type="button"
                            className="app-button app-button--ghost"
                            onClick={() => navigate('/coins/price-alert-settings')}
                        >
                            Back
                        </button>
                    </div>
                </form>
            </section>
        </div>
    );
}
