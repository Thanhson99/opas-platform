import { describe, expect, it } from 'vitest';
import {
    buildObservabilityMetricCards,
    buildModelRows,
    buildOperationalSummaryRows,
    buildProviderRows,
    buildStatusRows,
    formatMetric,
    formatTimestamp,
    resolveStatusTone,
} from './autoCodingObservability.helpers';

describe('autoCodingObservability helpers', () => {
    it('formats metrics and status tones for dashboard cards', () => {
        const cards = buildObservabilityMetricCards(
            {
                summary: {
                    active_tasks: 3,
                    runs_in_window: 12,
                    online_machines: 2,
                    failed_runs_in_window: 1,
                },
            },
            (key) => key,
        );

        expect(formatMetric(1200)).toBe('1,200');
        expect(cards).toHaveLength(4);
        expect(cards[3].tone).toBe('danger');
        expect(resolveStatusTone('blocked')).toBe('warning');
        expect(resolveStatusTone('critical')).toBe('danger');
        expect(resolveStatusTone('healthy')).toBe('success');
        expect(resolveStatusTone('completed')).toBe('success');
        expect(buildStatusRows({ task_statuses: { running: 2 } })[1]).toEqual({
            status: 'running',
            count: 2,
        });
    });

    it('builds operational summary rows', () => {
        const rows = buildOperationalSummaryRows(
            {
                operational_summary: {
                    critical_notifications: 1,
                    warning_notifications: 2,
                    failed_repositories: 3,
                },
            },
            (key) => key,
        );

        expect(rows).toContainEqual({
            key: 'critical_notifications',
            label: 'adminAutoCodingOps.operational.criticalNotifications',
            value: '1',
        });
        expect(rows[4].value).toBe('3');
    });

    it('normalizes provider rows and invalid timestamps', () => {
        const rows = buildProviderRows({
            ai_usage: {
                providers: {
                    codex: 2,
                    ollama: 5,
                },
            },
        });

        expect(rows[0]).toEqual({ name: 'ollama', count: 5 });
        expect(formatTimestamp(null)).toBe('-');
        expect(formatTimestamp('not-a-date')).toBe('-');
    });

    it('normalizes model rows', () => {
        const rows = buildModelRows({
            ai_usage: {
                models: {
                    'gpt-5': 4,
                    'gpt-5-mini': 7,
                },
            },
        });

        expect(rows[0]).toEqual({ name: 'gpt-5-mini', count: 7 });
    });
});
