/**
 * Format one integer-like metric for dashboard cards.
 *
 * @param {number | string | null | undefined} value
 * @returns {string}
 */
export function formatMetric(value) {
    const numberValue = Number(value ?? 0);

    return Number.isFinite(numberValue) ? new Intl.NumberFormat().format(numberValue) : '0';
}

/**
 * Format one ISO timestamp for compact admin display.
 *
 * @param {string | null | undefined} value
 * @param {string} language
 * @returns {string}
 */
export function formatTimestamp(value, language = 'en') {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat(language, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

/**
 * Resolve a visual tone for task and run statuses.
 *
 * @param {string | null | undefined} status
 * @returns {string}
 */
export function resolveStatusTone(status) {
    if (
        status === 'completed' ||
        status === 'online' ||
        status === 'idle' ||
        status === 'healthy'
    ) {
        return 'success';
    }

    if (status === 'failed' || status === 'offline' || status === 'critical') {
        return 'danger';
    }

    if (status === 'running' || status === 'busy') {
        return 'info';
    }

    if (
        status === 'blocked' ||
        status === 'stale' ||
        status === 'draining' ||
        status === 'warning'
    ) {
        return 'warning';
    }

    return 'neutral';
}

/**
 * Build dashboard metric cards from the observability report.
 *
 * @param {Record<string, any> | null} report
 * @param {(key: string) => string} t
 * @returns {Array<{key: string, icon: string, label: string, value: string, hint: string, tone: string}>}
 */
export function buildObservabilityMetricCards(report, t) {
    const summary = report?.summary ?? {};

    return [
        {
            key: 'active_tasks',
            icon: 'activity',
            label: t('adminAutoCodingOps.metrics.activeTasks'),
            value: formatMetric(summary.active_tasks),
            hint: t('adminAutoCodingOps.metrics.activeTasksHint'),
            tone: 'info',
        },
        {
            key: 'runs',
            icon: 'workflow',
            label: t('adminAutoCodingOps.metrics.runs'),
            value: formatMetric(summary.runs_in_window),
            hint: t('adminAutoCodingOps.metrics.windowHint'),
            tone: 'purple',
        },
        {
            key: 'machines',
            icon: 'bot',
            label: t('adminAutoCodingOps.metrics.onlineMachines'),
            value: formatMetric(summary.online_machines),
            hint: t('adminAutoCodingOps.metrics.onlineMachinesHint'),
            tone: 'success',
        },
        {
            key: 'failures',
            icon: 'alerts',
            label: t('adminAutoCodingOps.metrics.failedRuns'),
            value: formatMetric(summary.failed_runs_in_window),
            hint: t('adminAutoCodingOps.metrics.windowHint'),
            tone: Number(summary.failed_runs_in_window ?? 0) > 0 ? 'danger' : 'success',
        },
    ];
}

/**
 * Return the most useful provider usage rows.
 *
 * @param {Record<string, any> | null} report
 * @returns {Array<{name: string, count: number}>}
 */
export function buildProviderRows(report) {
    return Object.entries(report?.ai_usage?.providers ?? {})
        .map(([name, count]) => ({ name, count: Number(count) || 0 }))
        .sort((left, right) => right.count - left.count)
        .slice(0, 5);
}

/**
 * Return the most used AI model rows.
 *
 * @param {Record<string, any> | null} report
 * @returns {Array<{name: string, count: number}>}
 */
export function buildModelRows(report) {
    return Object.entries(report?.ai_usage?.models ?? {})
        .map(([name, count]) => ({ name, count: Number(count) || 0 }))
        .sort((left, right) => right.count - left.count)
        .slice(0, 5);
}

/**
 * Build task status distribution rows in a stable order.
 *
 * @param {Record<string, any> | null} report
 * @returns {Array<{status: string, count: number}>}
 */
export function buildStatusRows(report) {
    const statuses = report?.task_statuses ?? {};
    const order = ['pending', 'running', 'blocked', 'failed', 'completed', 'cancelled'];

    return order.map((status) => ({
        status,
        count: Number(statuses[status] ?? 0) || 0,
    }));
}

/**
 * Build operational summary rows in a stable review order.
 *
 * @param {Record<string, any> | null} report
 * @param {(key: string) => string} t
 * @returns {Array<{key: string, label: string, value: string}>}
 */
export function buildOperationalSummaryRows(report, t) {
    const summary = report?.operational_summary ?? {};
    const rows = [
        ['critical_notifications', 'criticalNotifications'],
        ['warning_notifications', 'warningNotifications'],
        ['offline_machines', 'offlineMachines'],
        ['stale_machines', 'staleMachines'],
        ['failed_repositories', 'failedRepositories'],
        ['validation_failures', 'validationFailures'],
    ];

    return rows.map(([key, translationKey]) => ({
        key,
        label: t(`adminAutoCodingOps.operational.${translationKey}`),
        value: formatMetric(summary[key]),
    }));
}
