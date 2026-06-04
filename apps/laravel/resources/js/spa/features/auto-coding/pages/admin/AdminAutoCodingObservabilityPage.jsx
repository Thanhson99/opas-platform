import { useCallback, useEffect, useMemo, useState } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';
import ErrorState from '../../../../components/ui/ErrorState';
import LoadingState from '../../../../components/ui/LoadingState';
import api from '../../../../lib/api';
import { useLanguage } from '../../../i18n/context/LanguageContext';
import {
    buildModelRows,
    buildObservabilityMetricCards,
    buildOperationalSummaryRows,
    buildProviderRows,
    buildStatusRows,
    formatMetric,
    formatTimestamp,
    resolveStatusTone,
} from './observability/autoCodingObservability.helpers';
import '../../../../../../scss/modules/_admin-auto-coding-ops.scss';

/**
 * Render the centralized auto-coding operations dashboard.
 *
 * @returns {import('react').JSX.Element}
 */
export default function AdminAutoCodingObservabilityPage() {
    const { language, t } = useLanguage();
    const [report, setReport] = useState(null);
    const [days, setDays] = useState(7);
    const [filters, setFilters] = useState({
        repositoryPath: '',
        machineKey: '',
    });
    const [filterDraft, setFilterDraft] = useState(filters);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const loadReport = useCallback(async () => {
        setLoading(true);

        try {
            const response = await api.get('/admin/auto-coding/observability', {
                params: {
                    days,
                    repository_path: filters.repositoryPath,
                    machine_key: filters.machineKey,
                },
                timeoutMs: 20000,
            });

            setReport(response.data.data ?? null);
            setError('');
        } catch (requestError) {
            setReport(null);
            setError(requestError?.response?.data?.message || t('adminAutoCodingOps.loadError'));
        } finally {
            setLoading(false);
        }
    }, [days, filters.machineKey, filters.repositoryPath, t]);

    useEffect(() => {
        void loadReport();
    }, [loadReport]);

    const handleFilterDraftChange = useCallback((field, value) => {
        setFilterDraft((current) => ({
            ...current,
            [field]: value,
        }));
    }, []);
    const handleFilterSubmit = useCallback(
        (event) => {
            event.preventDefault();

            setFilters({
                repositoryPath: filterDraft.repositoryPath.trim(),
                machineKey: filterDraft.machineKey.trim(),
            });
        },
        [filterDraft.machineKey, filterDraft.repositoryPath],
    );
    const handleFilterReset = useCallback(() => {
        const emptyFilters = {
            repositoryPath: '',
            machineKey: '',
        };

        setFilterDraft(emptyFilters);
        setFilters(emptyFilters);
    }, []);

    const metricCards = useMemo(() => buildObservabilityMetricCards(report, t), [report, t]);
    const operationalRows = useMemo(() => buildOperationalSummaryRows(report, t), [report, t]);
    const modelRows = useMemo(() => buildModelRows(report), [report]);
    const providerRows = useMemo(() => buildProviderRows(report), [report]);
    const statusRows = useMemo(() => buildStatusRows(report), [report]);
    const generatedAt = formatTimestamp(report?.generated_at, language);

    if (loading && report === null) {
        return <LoadingState label={t('adminAutoCodingOps.loading')} />;
    }

    if (error && report === null) {
        return <ErrorState title={t('adminAutoCodingOps.loadError')} message={error} />;
    }

    return (
        <div className="admin-auto-coding-ops">
            <header className="admin-auto-coding-ops__header">
                <div>
                    <p className="admin-auto-coding-ops__eyebrow">
                        {t('adminAutoCodingOps.eyebrow')}
                    </p>
                    <h1>{t('adminAutoCodingOps.title')}</h1>
                    <p>{t('adminAutoCodingOps.text')}</p>
                </div>
                <form className="admin-auto-coding-ops__actions" onSubmit={handleFilterSubmit}>
                    <label>
                        <span>{t('adminAutoCodingOps.window')}</span>
                        <select
                            value={days}
                            onChange={(event) => setDays(Number(event.target.value))}
                        >
                            <option value={1}>{t('adminAutoCodingOps.windows.one')}</option>
                            <option value={7}>{t('adminAutoCodingOps.windows.seven')}</option>
                            <option value={14}>{t('adminAutoCodingOps.windows.fourteen')}</option>
                            <option value={30}>{t('adminAutoCodingOps.windows.thirty')}</option>
                        </select>
                    </label>
                    <label>
                        <span>{t('adminAutoCodingOps.filters.repositoryPath')}</span>
                        <input
                            type="text"
                            value={filterDraft.repositoryPath}
                            list="admin-auto-coding-ops-repositories"
                            placeholder={t('adminAutoCodingOps.filters.repositoryPlaceholder')}
                            onChange={(event) =>
                                handleFilterDraftChange('repositoryPath', event.target.value)
                            }
                        />
                        <datalist id="admin-auto-coding-ops-repositories">
                            {(report?.filter_options?.repository_paths ?? []).map(
                                (repositoryPath) => (
                                    <option key={repositoryPath} value={repositoryPath} />
                                ),
                            )}
                        </datalist>
                    </label>
                    <label>
                        <span>{t('adminAutoCodingOps.filters.machineKey')}</span>
                        <input
                            type="text"
                            value={filterDraft.machineKey}
                            list="admin-auto-coding-ops-machines"
                            placeholder={t('adminAutoCodingOps.filters.machinePlaceholder')}
                            onChange={(event) =>
                                handleFilterDraftChange('machineKey', event.target.value)
                            }
                        />
                        <datalist id="admin-auto-coding-ops-machines">
                            {(report?.filter_options?.machines ?? []).map((machine) => (
                                <option
                                    key={machine.machine_key}
                                    value={machine.machine_key}
                                    label={`${machine.hostname} · ${machine.derived_status}`}
                                />
                            ))}
                        </datalist>
                    </label>
                    <button type="submit" className="app-button">
                        <AppIcon name="search" />
                        <span>{t('adminAutoCodingOps.filters.apply')}</span>
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={handleFilterReset}
                    >
                        <AppIcon name="x" />
                        <span>{t('adminAutoCodingOps.filters.clear')}</span>
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={loadReport}
                    >
                        <AppIcon name="refresh" />
                        <span>{t('adminAutoCodingOps.refresh')}</span>
                    </button>
                </form>
            </header>

            {error ? <div className="admin-auto-coding-ops__notice">{error}</div> : null}

            <section className="admin-auto-coding-ops__metrics">
                {metricCards.map((card) => (
                    <article
                        key={card.key}
                        className={`admin-auto-coding-ops__metric is-${card.tone}`}
                    >
                        <span className="admin-auto-coding-ops__metric-icon">
                            <AppIcon name={card.icon} />
                        </span>
                        <span>{card.label}</span>
                        <strong>{card.value}</strong>
                        <small>{card.hint}</small>
                    </article>
                ))}
            </section>

            <Panel title={t('adminAutoCodingOps.sections.operationalReport')} icon="shield">
                <div className="admin-auto-coding-ops__operational">
                    <div className="admin-auto-coding-ops__operational-status">
                        <span>{t('adminAutoCodingOps.operational.health')}</span>
                        <Badge value={report?.operational_summary?.health ?? 'healthy'} />
                    </div>
                    <div className="admin-auto-coding-ops__operational-grid">
                        {operationalRows.map((row) => (
                            <div key={row.key}>
                                <span>{row.label}</span>
                                <strong>{row.value}</strong>
                            </div>
                        ))}
                    </div>
                </div>
            </Panel>

            <Panel title={t('adminAutoCodingOps.sections.reviewActions')} icon="target">
                <CompactList
                    items={report?.review_actions ?? []}
                    emptyText={t('adminAutoCodingOps.empty.reviewActions')}
                    renderItem={(action) => (
                        <>
                            <strong>{action.title}</strong>
                            <span>{action.message}</span>
                            <small>
                                {action.priority} · {action.type}
                            </small>
                        </>
                    )}
                />
            </Panel>

            <section className="admin-auto-coding-ops__grid">
                <Panel title={t('adminAutoCodingOps.sections.statusDistribution')} icon="activity">
                    <div className="admin-auto-coding-ops__status-grid">
                        {statusRows.map((row) => (
                            <div key={row.status} className="admin-auto-coding-ops__status-row">
                                <Badge value={row.status} />
                                <strong>{formatMetric(row.count)}</strong>
                            </div>
                        ))}
                    </div>
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.dailyActivity')} icon="calendar">
                    <CompactList
                        items={(report?.daily_activity ?? []).slice(-7).reverse()}
                        emptyText={t('adminAutoCodingOps.empty.dailyActivity')}
                        renderItem={(day) => (
                            <>
                                <strong>{day.date}</strong>
                                <span>
                                    {t('adminAutoCodingOps.daily.tasks')}{' '}
                                    {formatMetric(day.tasks_created)} ·{' '}
                                    {t('adminAutoCodingOps.daily.runs')}{' '}
                                    {formatMetric(day.runs_created)}
                                </span>
                                <small>
                                    {t('adminAutoCodingOps.daily.completed')}{' '}
                                    {formatMetric(day.completed_runs)} ·{' '}
                                    {t('adminAutoCodingOps.daily.failed')}{' '}
                                    {formatMetric(day.failed_runs)}
                                </small>
                            </>
                        )}
                    />
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.queueHealth')} icon="clock">
                    <div className="admin-auto-coding-ops__usage">
                        <div>
                            <span>{t('adminAutoCodingOps.queue.active')}</span>
                            <strong>{formatMetric(report?.queue_health?.active_count)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.queue.oldestAge')}</span>
                            <strong>
                                {formatMinutes(report?.queue_health?.oldest_age_minutes)}
                            </strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.queue.averageAge')}</span>
                            <strong>
                                {formatMinutes(report?.queue_health?.average_age_minutes)}
                            </strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.queue.blocked')}</span>
                            <strong>
                                {formatMetric(report?.queue_health?.status_counts?.blocked)}
                            </strong>
                        </div>
                    </div>
                    <CompactList
                        items={(report?.queue_health?.oldest_tasks ?? []).slice(0, 5)}
                        emptyText={t('adminAutoCodingOps.empty.queueHealth')}
                        renderItem={(task) => (
                            <>
                                <strong>{task.summary || `#${task.id}`}</strong>
                                <span>
                                    {task.repository_path} · {task.machine_key || '-'} ·{' '}
                                    {formatMinutes(task.age_minutes)}
                                </span>
                                <small>{task.status}</small>
                            </>
                        )}
                    />
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.notifications')} icon="alerts">
                    <div className="admin-auto-coding-ops__usage">
                        <div>
                            <span>{t('adminAutoCodingOps.notifications.total')}</span>
                            <strong>{formatMetric(report?.notification_summary?.total)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.notifications.critical')}</span>
                            <strong>
                                {formatMetric(
                                    report?.notification_summary?.severity_counts?.critical,
                                )}
                            </strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.notifications.warning')}</span>
                            <strong>
                                {formatMetric(
                                    report?.notification_summary?.severity_counts?.warning,
                                )}
                            </strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.notifications.types')}</span>
                            <strong>
                                {formatMetric(
                                    Object.keys(report?.notification_summary?.type_counts ?? {})
                                        .length,
                                )}
                            </strong>
                        </div>
                    </div>
                    <CompactList
                        items={Object.entries(report?.notification_summary?.type_counts ?? {}).map(
                            ([type, count]) => ({ id: type, type, count }),
                        )}
                        emptyText={t('adminAutoCodingOps.empty.notificationTypes')}
                        renderItem={(typeRow) => (
                            <>
                                <strong>{typeRow.type}</strong>
                                <span>
                                    {t('adminAutoCodingOps.notifications.count')}{' '}
                                    {formatMetric(typeRow.count)}
                                </span>
                            </>
                        )}
                    />
                    <div className="admin-auto-coding-ops__stack">
                        {(report?.notifications ?? []).length > 0 ? (
                            report.notifications.map((item, index) => (
                                <article
                                    key={`${item.type}-${item.task_id ?? item.machine_id ?? index}`}
                                    className={`admin-auto-coding-ops__event is-${item.severity}`}
                                >
                                    <strong>{item.title}</strong>
                                    <span>{item.message}</span>
                                    <small>{formatTimestamp(item.created_at, language)}</small>
                                </article>
                            ))
                        ) : (
                            <EmptyText text={t('adminAutoCodingOps.empty.notifications')} />
                        )}
                    </div>
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.aiUsage')} icon="analytics">
                    <div className="admin-auto-coding-ops__usage">
                        <div>
                            <span>{t('adminAutoCodingOps.usage.runs')}</span>
                            <strong>{formatMetric(report?.ai_usage?.run_count)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.usage.totalTokens')}</span>
                            <strong>{formatMetric(report?.ai_usage?.tokens?.total_tokens)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.usage.promptTokens')}</span>
                            <strong>{formatMetric(report?.ai_usage?.tokens?.prompt_tokens)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.usage.completionTokens')}</span>
                            <strong>
                                {formatMetric(report?.ai_usage?.tokens?.completion_tokens)}
                            </strong>
                        </div>
                    </div>
                    <div className="admin-auto-coding-ops__provider-list">
                        {providerRows.length > 0 ? (
                            providerRows.map((row) => (
                                <div key={row.name} className="admin-auto-coding-ops__provider-row">
                                    <span>{row.name}</span>
                                    <strong>{formatMetric(row.count)}</strong>
                                </div>
                            ))
                        ) : (
                            <EmptyText text={t('adminAutoCodingOps.empty.usage')} />
                        )}
                    </div>
                    <CompactList
                        items={modelRows.map((row) => ({ id: row.name, ...row }))}
                        emptyText={t('adminAutoCodingOps.empty.models')}
                        renderItem={(model) => (
                            <>
                                <strong>{model.name}</strong>
                                <span>
                                    {t('adminAutoCodingOps.usage.modelRuns')}{' '}
                                    {formatMetric(model.count)}
                                </span>
                            </>
                        )}
                    />
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.repositories')} icon="target">
                    <CompactList
                        items={(report?.repository_summary ?? []).slice(0, 8)}
                        emptyText={t('adminAutoCodingOps.empty.repositories')}
                        renderItem={(repository) => (
                            <>
                                <strong>{repository.repository_path}</strong>
                                <span>
                                    {t('adminAutoCodingOps.repository.tasks')}{' '}
                                    {formatMetric(repository.task_count)} ·{' '}
                                    {t('adminAutoCodingOps.repository.active')}{' '}
                                    {formatMetric(repository.active_task_count)} ·{' '}
                                    {t('adminAutoCodingOps.repository.failed')}{' '}
                                    {formatMetric(repository.failed_task_count)}
                                </span>
                                <small>
                                    {t('adminAutoCodingOps.repository.runs')}{' '}
                                    {formatMetric(repository.run_count)} ·{' '}
                                    {t('adminAutoCodingOps.repository.failedRuns')}{' '}
                                    {formatMetric(repository.failed_run_count)}
                                </small>
                            </>
                        )}
                    />
                </Panel>
            </section>

            <Panel title={t('adminAutoCodingOps.sections.machineHealth')} icon="bot">
                <div className="admin-auto-coding-ops__usage">
                    <div>
                        <span>{t('adminAutoCodingOps.machine.runningSlots')}</span>
                        <strong>{formatMetric(report?.machine_capacity?.running_slots)}</strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.machine.availableSlots')}</span>
                        <strong>{formatMetric(report?.machine_capacity?.available_slots)}</strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.machine.utilization')}</span>
                        <strong>
                            {formatPercent(report?.machine_capacity?.utilization_percent)}
                        </strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.machine.atCapacity')}</span>
                        <strong>
                            {formatMetric(report?.machine_capacity?.machines_at_capacity)}
                        </strong>
                    </div>
                </div>
                <div className="admin-auto-coding-ops__usage">
                    <div>
                        <span>{t('adminAutoCodingOps.resources.reportedMachines')}</span>
                        <strong>{formatMetric(report?.resource_summary?.reported_machines)}</strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.resources.maxDisk')}</span>
                        <strong>
                            {formatPercent(report?.resource_summary?.metrics?.disk_percent?.max)}
                        </strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.resources.maxMemory')}</span>
                        <strong>
                            {formatPercent(report?.resource_summary?.metrics?.memory_percent?.max)}
                        </strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.resources.maxLoad')}</span>
                        <strong>
                            {formatOptionalMetric(
                                report?.resource_summary?.metrics?.load_average?.max,
                            )}
                        </strong>
                    </div>
                </div>
                <CompactList
                    items={(report?.resource_summary?.highest_pressure ?? []).slice(0, 6)}
                    emptyText={t('adminAutoCodingOps.empty.resources')}
                    renderItem={(resource) => (
                        <>
                            <strong>{resource.machine_key}</strong>
                            <span>
                                {resource.metric} · {formatOptionalMetric(resource.value)}
                            </span>
                            <small>{resource.hostname}</small>
                        </>
                    )}
                />
                <div className="admin-auto-coding-ops__status-grid">
                    {Object.entries(report?.machine_fleet?.derived_status_counts ?? {}).map(
                        ([status, count]) => (
                            <div key={status} className="admin-auto-coding-ops__status-row">
                                <Badge value={status} />
                                <strong>{formatMetric(count)}</strong>
                            </div>
                        ),
                    )}
                </div>
                <CompactList
                    items={Object.entries(report?.machine_fleet?.operating_system_counts ?? {}).map(
                        ([name, count]) => ({ id: name, name, count }),
                    )}
                    emptyText={t('adminAutoCodingOps.empty.machineFleet')}
                    renderItem={(item) => (
                        <>
                            <strong>{item.name}</strong>
                            <span>
                                {t('adminAutoCodingOps.machine.machineCount')}{' '}
                                {formatMetric(item.count)}
                            </span>
                        </>
                    )}
                />
                <CompactList
                    items={Object.entries(report?.machine_capabilities?.capabilities ?? {}).map(
                        ([name, counts]) => ({ id: name, name, ...counts }),
                    )}
                    emptyText={t('adminAutoCodingOps.empty.machineCapabilities')}
                    renderItem={(item) => (
                        <>
                            <strong>{item.name}</strong>
                            <span>
                                {t('adminAutoCodingOps.machine.enabled')}{' '}
                                {formatMetric(item.enabled)} ·{' '}
                                {t('adminAutoCodingOps.machine.disabled')}{' '}
                                {formatMetric(item.disabled)}
                            </span>
                        </>
                    )}
                />
                <CompactList
                    items={(report?.workspace_bindings?.repositories ?? []).slice(0, 6)}
                    emptyText={t('adminAutoCodingOps.empty.workspaceBindings')}
                    renderItem={(repository) => (
                        <>
                            <strong>{repository.repository_path}</strong>
                            <span>
                                {t('adminAutoCodingOps.machine.bindings')}{' '}
                                {formatMetric(repository.binding_count)} ·{' '}
                                {t('adminAutoCodingOps.machine.machineCount')}{' '}
                                {formatMetric(repository.machine_count)}
                            </span>
                            <small>
                                {t('adminAutoCodingOps.machine.workspaces')}{' '}
                                {formatMetric(repository.workspace_count)}
                            </small>
                        </>
                    )}
                />
                <div className="admin-auto-coding-ops__machine-grid">
                    {(report?.machine_health ?? []).map((machine) => (
                        <article key={machine.id} className="admin-auto-coding-ops__machine">
                            <div>
                                <strong>{machine.machine_key}</strong>
                                <span>{machine.hostname}</span>
                            </div>
                            <Badge value={machine.derived_status} />
                            <dl>
                                <div>
                                    <dt>{t('adminAutoCodingOps.machine.os')}</dt>
                                    <dd>{machine.operating_system}</dd>
                                </div>
                                <div>
                                    <dt>{t('adminAutoCodingOps.machine.workspaces')}</dt>
                                    <dd>{formatMetric(machine.workspace_count)}</dd>
                                </div>
                                <div>
                                    <dt>{t('adminAutoCodingOps.machine.capacity')}</dt>
                                    <dd>
                                        {formatMetric(machine.capacity?.running)} /{' '}
                                        {formatMetric(machine.capacity?.max_parallel)}
                                        <span>
                                            {formatPercent(machine.capacity?.utilization_percent)}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt>{t('adminAutoCodingOps.machine.disk')}</dt>
                                    <dd>
                                        {formatPercent(machine.resources?.disk_percent)}
                                        {machine.resources?.disk_free_mb ? (
                                            <span>
                                                {formatMb(machine.resources.disk_free_mb)}{' '}
                                                {t('adminAutoCodingOps.machine.free')}
                                            </span>
                                        ) : null}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{t('adminAutoCodingOps.machine.memory')}</dt>
                                    <dd>
                                        {formatMb(machine.resources?.process_memory_mb)}
                                        {machine.resources?.process_peak_memory_mb ? (
                                            <span>
                                                {t('adminAutoCodingOps.machine.peak')}{' '}
                                                {formatMb(machine.resources.process_peak_memory_mb)}
                                            </span>
                                        ) : null}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{t('adminAutoCodingOps.machine.load')}</dt>
                                    <dd>{formatOptionalMetric(machine.resources?.load_average)}</dd>
                                </div>
                            </dl>
                        </article>
                    ))}
                    {(report?.machine_health ?? []).length === 0 ? (
                        <EmptyText text={t('adminAutoCodingOps.empty.machines')} />
                    ) : null}
                </div>
            </Panel>

            <Panel title={t('adminAutoCodingOps.sections.runPerformance')} icon="clock">
                <div className="admin-auto-coding-ops__usage">
                    <div>
                        <span>{t('adminAutoCodingOps.performance.completedRuns')}</span>
                        <strong>{formatMetric(report?.run_performance?.run_count)}</strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.performance.averageDuration')}</span>
                        <strong>
                            {formatDuration(report?.run_performance?.average_duration_seconds)}
                        </strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.performance.fastestDuration')}</span>
                        <strong>
                            {formatDuration(report?.run_performance?.min_duration_seconds)}
                        </strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.performance.slowestDuration')}</span>
                        <strong>
                            {formatDuration(report?.run_performance?.max_duration_seconds)}
                        </strong>
                    </div>
                </div>
                <CompactList
                    items={(report?.run_performance?.slowest_runs ?? []).slice(0, 5)}
                    emptyText={t('adminAutoCodingOps.empty.runPerformance')}
                    renderItem={(run) => (
                        <>
                            <strong>{run.task_summary || `#${run.task_id}`}</strong>
                            <span>
                                #{run.id} · {run.machine_key || '-'} ·{' '}
                                {formatDuration(run.duration_seconds)}
                            </span>
                        </>
                    )}
                />
            </Panel>

            <Panel title={t('adminAutoCodingOps.sections.reliability')} icon="analytics">
                <div className="admin-auto-coding-ops__usage">
                    <div>
                        <span>{t('adminAutoCodingOps.reliability.runs')}</span>
                        <strong>{formatMetric(report?.reliability_summary?.run_count)}</strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.reliability.successRate')}</span>
                        <strong>
                            {formatPercent(report?.reliability_summary?.success_rate_percent)}
                        </strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.reliability.failureRate')}</span>
                        <strong>
                            {formatPercent(report?.reliability_summary?.failure_rate_percent)}
                        </strong>
                    </div>
                    <div>
                        <span>{t('adminAutoCodingOps.reliability.failed')}</span>
                        <strong>
                            {formatMetric(report?.reliability_summary?.status_counts?.failed)}
                        </strong>
                    </div>
                </div>
                <section className="admin-auto-coding-ops__split">
                    <CompactList
                        items={(report?.reliability_summary?.machines ?? []).slice(0, 4)}
                        emptyText={t('adminAutoCodingOps.empty.reliabilityMachines')}
                        renderItem={(row) => (
                            <>
                                <strong>{row.name}</strong>
                                <span>
                                    {t('adminAutoCodingOps.reliability.failed')}{' '}
                                    {formatMetric(row.failed)} ·{' '}
                                    {t('adminAutoCodingOps.reliability.successRate')}{' '}
                                    {formatPercent(row.success_rate_percent)}
                                </span>
                            </>
                        )}
                    />
                    <CompactList
                        items={(report?.reliability_summary?.providers ?? []).slice(0, 4)}
                        emptyText={t('adminAutoCodingOps.empty.reliabilityProviders')}
                        renderItem={(row) => (
                            <>
                                <strong>{row.name}</strong>
                                <span>
                                    {t('adminAutoCodingOps.reliability.failed')}{' '}
                                    {formatMetric(row.failed)} ·{' '}
                                    {t('adminAutoCodingOps.reliability.failureRate')}{' '}
                                    {formatPercent(row.failure_rate_percent)}
                                </span>
                            </>
                        )}
                    />
                </section>
            </Panel>

            <section className="admin-auto-coding-ops__split">
                <Panel title={t('adminAutoCodingOps.sections.recentTasks')} icon="history">
                    <TaskTable tasks={report?.recent_tasks ?? []} language={language} t={t} />
                </Panel>
                <Panel title={t('adminAutoCodingOps.sections.recentRuns')} icon="terminal">
                    <RunTable runs={report?.recent_runs ?? []} language={language} t={t} />
                </Panel>
            </section>

            <section className="admin-auto-coding-ops__grid">
                <Panel title={t('adminAutoCodingOps.sections.timeline')} icon="history">
                    <CompactList
                        items={(report?.activity_timeline ?? []).slice(0, 10)}
                        emptyText={t('adminAutoCodingOps.empty.timeline')}
                        renderItem={(event) => (
                            <>
                                <strong>{event.title}</strong>
                                <span>
                                    {event.type} · {event.message}
                                    {event.machine_key ? ` · ${event.machine_key}` : ''}
                                </span>
                                <small>{formatTimestamp(event.occurred_at, language)}</small>
                            </>
                        )}
                    />
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.failures')} icon="alerts">
                    <div className="admin-auto-coding-ops__usage">
                        <div>
                            <span>{t('adminAutoCodingOps.failures.total')}</span>
                            <strong>{formatMetric(report?.failure_summary?.total)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.failures.categories')}</span>
                            <strong>
                                {formatMetric(
                                    Object.keys(report?.failure_summary?.categories ?? {}).length,
                                )}
                            </strong>
                        </div>
                    </div>
                    <CompactList
                        items={(report?.failure_summary?.recent ?? []).slice(0, 5)}
                        emptyText={t('adminAutoCodingOps.empty.failures')}
                        renderItem={(failure) => (
                            <>
                                <strong>{failure.summary}</strong>
                                <span>
                                    {failure.category} · {failure.message || `#${failure.id}`}
                                </span>
                            </>
                        )}
                    />
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.validation')} icon="check">
                    <div className="admin-auto-coding-ops__status-grid">
                        {Object.entries(report?.validation_summary?.statuses ?? {}).map(
                            ([status, count]) => (
                                <div key={status} className="admin-auto-coding-ops__status-row">
                                    <Badge value={status} />
                                    <strong>{formatMetric(count)}</strong>
                                </div>
                            ),
                        )}
                    </div>
                    <CompactList
                        items={(report?.validation_summary?.recent_failures ?? []).slice(0, 5)}
                        emptyText={t('adminAutoCodingOps.empty.validation')}
                        renderItem={(failure) => (
                            <>
                                <strong>{failure.task_summary || `#${failure.task_id}`}</strong>
                                <span>{failure.message || `run #${failure.run_id}`}</span>
                            </>
                        )}
                    />
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.errors')} icon="alerts">
                    <div className="admin-auto-coding-ops__usage">
                        <div>
                            <span>{t('adminAutoCodingOps.errors.total')}</span>
                            <strong>{formatMetric(report?.error_summary?.total)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.errors.unique')}</span>
                            <strong>
                                {formatMetric((report?.error_summary?.messages ?? []).length)}
                            </strong>
                        </div>
                    </div>
                    <CompactList
                        items={(report?.error_summary?.messages ?? []).slice(0, 6)}
                        emptyText={t('adminAutoCodingOps.empty.errors')}
                        renderItem={(error) => (
                            <>
                                <strong>{error.source}</strong>
                                <span>{error.message}</span>
                                <small>
                                    {t('adminAutoCodingOps.errors.count')}{' '}
                                    {formatMetric(error.count)}
                                </small>
                            </>
                        )}
                    />
                </Panel>
            </section>

            <section className="admin-auto-coding-ops__grid">
                <Panel title={t('adminAutoCodingOps.sections.reviewPackages')} icon="target">
                    <CompactList
                        items={(report?.review_packages ?? []).slice(0, 8)}
                        emptyText={t('adminAutoCodingOps.empty.reviewPackages')}
                        renderItem={(reviewPackage) => (
                            <>
                                <strong>
                                    {reviewPackage.task_summary || `#${reviewPackage.task_id}`}
                                </strong>
                                <span>
                                    #{reviewPackage.run_id} · {reviewPackage.machine_key || '-'} ·{' '}
                                    {t('adminAutoCodingOps.review.files')}{' '}
                                    {formatMetric(reviewPackage.changed_file_count)} ·{' '}
                                    {t('adminAutoCodingOps.review.artifacts')}{' '}
                                    {formatMetric(reviewPackage.artifact_count)}
                                </span>
                                <small>{reviewPackage.repository_path || '-'}</small>
                            </>
                        )}
                    />
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.changedFiles')} icon="edit">
                    <div className="admin-auto-coding-ops__usage">
                        <div>
                            <span>{t('adminAutoCodingOps.review.totalFiles')}</span>
                            <strong>{formatMetric(report?.changed_files?.total)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.review.fileTypes')}</span>
                            <strong>
                                {formatMetric(
                                    Object.keys(report?.changed_files?.extension_counts ?? {})
                                        .length,
                                )}
                            </strong>
                        </div>
                    </div>
                    <CompactList
                        items={Object.entries(report?.changed_files?.status_counts ?? {}).map(
                            ([status, count]) => ({ id: status, status, count }),
                        )}
                        emptyText={t('adminAutoCodingOps.empty.changedFileSummary')}
                        renderItem={(status) => (
                            <>
                                <strong>{status.status}</strong>
                                <span>
                                    {t('adminAutoCodingOps.review.files')}{' '}
                                    {formatMetric(status.count)}
                                </span>
                            </>
                        )}
                    />
                    <CompactList
                        items={(report?.changed_files?.files ?? []).slice(0, 8)}
                        emptyText={t('adminAutoCodingOps.empty.changedFiles')}
                        renderItem={(file) => (
                            <>
                                <strong>{file.path}</strong>
                                <span>
                                    {file.status} · #{file.task_id}
                                </span>
                            </>
                        )}
                    />
                </Panel>
                <Panel title={t('adminAutoCodingOps.sections.artifacts')} icon="history">
                    <div className="admin-auto-coding-ops__usage">
                        <div>
                            <span>{t('adminAutoCodingOps.review.totalArtifacts')}</span>
                            <strong>{formatMetric(report?.artifacts?.total)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.review.artifactTypes')}</span>
                            <strong>
                                {formatMetric(Object.keys(report?.artifacts?.types ?? {}).length)}
                            </strong>
                        </div>
                    </div>
                    <CompactList
                        items={Object.entries(report?.artifacts?.types ?? {}).map(
                            ([type, count]) => ({ id: type, type, count }),
                        )}
                        emptyText={t('adminAutoCodingOps.empty.artifactSummary')}
                        renderItem={(artifactType) => (
                            <>
                                <strong>{artifactType.type}</strong>
                                <span>
                                    {t('adminAutoCodingOps.review.artifacts')}{' '}
                                    {formatMetric(artifactType.count)}
                                </span>
                            </>
                        )}
                    />
                    <CompactList
                        items={(report?.artifacts?.recent ?? []).slice(0, 8)}
                        emptyText={t('adminAutoCodingOps.empty.artifacts')}
                        renderItem={(artifact) => (
                            <>
                                <strong>{artifact.label}</strong>
                                <span>
                                    {artifact.type} · #{artifact.task_id ?? artifact.task_run_id}
                                </span>
                            </>
                        )}
                    />
                </Panel>
            </section>

            <section className="admin-auto-coding-ops__grid">
                <Panel title={t('adminAutoCodingOps.sections.executionSummary')} icon="workflow">
                    <div className="admin-auto-coding-ops__usage">
                        <div>
                            <span>{t('adminAutoCodingOps.execution.totalSteps')}</span>
                            <strong>{formatMetric(report?.execution_summary?.total_steps)}</strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.execution.retryableSteps')}</span>
                            <strong>
                                {formatMetric(report?.execution_summary?.retryable_steps)}
                            </strong>
                        </div>
                        <div>
                            <span>{t('adminAutoCodingOps.execution.maxAttempt')}</span>
                            <strong>{formatMetric(report?.execution_summary?.max_attempt)}</strong>
                        </div>
                    </div>
                    <div className="admin-auto-coding-ops__status-grid">
                        {Object.entries(report?.execution_summary?.status_counts ?? {}).map(
                            ([status, count]) => (
                                <div key={status} className="admin-auto-coding-ops__status-row">
                                    <Badge value={status} />
                                    <strong>{formatMetric(count)}</strong>
                                </div>
                            ),
                        )}
                    </div>
                    <CompactList
                        items={Object.entries(
                            report?.execution_summary?.failed_or_blocked_steps ?? {},
                        ).map(([step, count]) => ({ id: step, step, count }))}
                        emptyText={t('adminAutoCodingOps.empty.executionSummary')}
                        renderItem={(step) => (
                            <>
                                <strong>{step.step}</strong>
                                <span>
                                    {t('adminAutoCodingOps.execution.failedOrBlocked')}{' '}
                                    {formatMetric(step.count)}
                                </span>
                            </>
                        )}
                    />
                </Panel>

                <Panel title={t('adminAutoCodingOps.sections.executionLogs')} icon="workflow">
                    <CompactList
                        items={(report?.execution_logs ?? []).slice(0, 8)}
                        emptyText={t('adminAutoCodingOps.empty.logs')}
                        renderItem={(log) => (
                            <>
                                <strong>{log.step_key}</strong>
                                <span>
                                    {log.status} · {t('adminAutoCodingOps.logs.attempt')}{' '}
                                    {log.attempt}
                                </span>
                            </>
                        )}
                    />
                </Panel>
            </section>

            <footer className="admin-auto-coding-ops__footer">
                {t('adminAutoCodingOps.generatedAt')} {generatedAt}
            </footer>
        </div>
    );
}

/**
 * Render a titled dashboard panel.
 *
 * @param {{title: string, icon: string, children: import('react').ReactNode}} props
 * @returns {import('react').JSX.Element}
 */
function Panel({ title, icon, children }) {
    return (
        <section className="admin-auto-coding-ops__panel">
            <header>
                <AppIcon name={icon} />
                <h2>{title}</h2>
            </header>
            {children}
        </section>
    );
}

/**
 * Render a compact empty state line.
 *
 * @param {{text: string}} props
 * @returns {import('react').JSX.Element}
 */
function EmptyText({ text }) {
    return <p className="admin-auto-coding-ops__empty">{text}</p>;
}

/**
 * Render a status badge.
 *
 * @param {{value: string}} props
 * @returns {import('react').JSX.Element}
 */
function Badge({ value }) {
    return (
        <span className={`admin-auto-coding-ops__badge is-${resolveStatusTone(value)}`}>
            {value || 'unknown'}
        </span>
    );
}

/**
 * Render recent task history rows.
 *
 * @param {{tasks: Array<Record<string, any>>, language: string, t: (key: string) => string}} props
 * @returns {import('react').JSX.Element}
 */
function TaskTable({ tasks, language, t }) {
    if (tasks.length === 0) {
        return <EmptyText text={t('adminAutoCodingOps.empty.tasks')} />;
    }

    return (
        <div className="admin-auto-coding-ops__table-wrap">
            <table className="admin-auto-coding-ops__table">
                <thead>
                    <tr>
                        <th>{t('adminAutoCodingOps.table.task')}</th>
                        <th>{t('adminAutoCodingOps.table.status')}</th>
                        <th>{t('adminAutoCodingOps.table.machine')}</th>
                        <th>{t('adminAutoCodingOps.table.runs')}</th>
                        <th>{t('adminAutoCodingOps.table.created')}</th>
                    </tr>
                </thead>
                <tbody>
                    {tasks.map((task) => (
                        <tr key={task.id}>
                            <td>
                                <strong>{task.summary}</strong>
                                <span>{task.issue_key || task.repository_path}</span>
                            </td>
                            <td>
                                <Badge value={task.status} />
                            </td>
                            <td>{task.assigned_machine?.machine_key || '-'}</td>
                            <td>{formatMetric(task.run_count)}</td>
                            <td>{formatTimestamp(task.created_at, language)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Render recent run history rows.
 *
 * @param {{runs: Array<Record<string, any>>, language: string, t: (key: string) => string}} props
 * @returns {import('react').JSX.Element}
 */
function RunTable({ runs, language, t }) {
    if (runs.length === 0) {
        return <EmptyText text={t('adminAutoCodingOps.empty.runs')} />;
    }

    return (
        <div className="admin-auto-coding-ops__table-wrap">
            <table className="admin-auto-coding-ops__table">
                <thead>
                    <tr>
                        <th>{t('adminAutoCodingOps.table.run')}</th>
                        <th>{t('adminAutoCodingOps.table.status')}</th>
                        <th>{t('adminAutoCodingOps.table.provider')}</th>
                        <th>{t('adminAutoCodingOps.table.files')}</th>
                        <th>{t('adminAutoCodingOps.table.completed')}</th>
                    </tr>
                </thead>
                <tbody>
                    {runs.map((run) => (
                        <tr key={run.id}>
                            <td>
                                <strong>#{run.id}</strong>
                                <span>{run.task_summary || `#${run.task_id}`}</span>
                            </td>
                            <td>
                                <Badge value={run.status} />
                            </td>
                            <td>{run.provider}</td>
                            <td>{formatMetric(run.changed_file_count)}</td>
                            <td>{formatTimestamp(run.completed_at || run.started_at, language)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Render a compact review list.
 *
 * @param {{
 *   items: Array<Record<string, any>>,
 *   emptyText: string,
 *   renderItem: (item: Record<string, any>) => import('react').ReactNode,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function CompactList({ items, emptyText, renderItem }) {
    if (items.length === 0) {
        return <EmptyText text={emptyText} />;
    }

    return (
        <div className="admin-auto-coding-ops__compact-list">
            {items.map((item, index) => (
                <article key={item.id ?? `${item.path}-${index}`}>{renderItem(item)}</article>
            ))}
        </div>
    );
}

/**
 * Format one optional percentage metric.
 *
 * @param {number | string | null | undefined} value
 * @returns {string}
 */
function formatPercent(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return `${formatMetric(value)}%`;
}

/**
 * Format one optional megabyte metric.
 *
 * @param {number | string | null | undefined} value
 * @returns {string}
 */
function formatMb(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return `${formatMetric(value)} MB`;
}

/**
 * Format a duration in seconds for compact performance summaries.
 *
 * @param {number | string | null | undefined} value
 * @returns {string}
 */
function formatDuration(value) {
    const seconds = Number(value ?? 0);

    if (!Number.isFinite(seconds) || seconds <= 0) {
        return '-';
    }

    if (seconds < 60) {
        return `${formatMetric(Math.round(seconds))}s`;
    }

    return `${formatMetric(Math.round(seconds / 60))}m`;
}

/**
 * Format a duration in minutes for queue aging summaries.
 *
 * @param {number | string | null | undefined} value
 * @returns {string}
 */
function formatMinutes(value) {
    const minutes = Number(value ?? 0);

    if (!Number.isFinite(minutes) || minutes <= 0) {
        return '0m';
    }

    if (minutes < 60) {
        return `${formatMetric(Math.round(minutes))}m`;
    }

    return `${formatMetric(Math.round(minutes / 60))}h`;
}

/**
 * Format an optional scalar metric.
 *
 * @param {number | string | null | undefined} value
 * @returns {string}
 */
function formatOptionalMetric(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return formatMetric(value);
}
