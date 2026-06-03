import { describe, expect, it } from 'vitest';
import { buildWorkspaceDashboardData } from './workspaceDashboard.data';

const translations = {
    'dashboard.stats.totalProfit.label': 'Total profit',
    'dashboard.stats.totalProfit.note': '(30 days)',
    'dashboard.stats.totalProfit.deltaText': 'vs previous period',
    'dashboard.stats.dailyProfit.label': 'Daily profit',
    'dashboard.stats.dailyProfit.deltaText': 'vs yesterday',
    'dashboard.stats.activeAutomations.label': 'Active automations',
    'dashboard.stats.activeAutomations.deltaText': 'vs yesterday',
    'dashboard.stats.savedTime.label': 'Saved time',
    'dashboard.stats.savedTime.deltaText': 'vs previous period',
    'dashboard.highlights.workflow.title': 'Workflow',
    'dashboard.highlights.workflow.text': 'Workflow text',
    'dashboard.highlights.analytics.title': 'Analytics',
    'dashboard.highlights.analytics.text': 'Analytics text',
    'dashboard.highlights.optimization.title': 'Optimization',
    'dashboard.highlights.optimization.text': 'Optimization text',
    'nav.coins': 'Coins',
    'dashboard.coinsText': 'Coin tools',
    'nav.stocks': 'Stocks',
    'dashboard.stocksText': 'Stock tools',
    'nav.contentKeywords': 'Keywords',
    'dashboard.keywordsText': 'Keyword tools',
    'nav.trendingVideos': 'Videos',
    'dashboard.videosText': 'Video tools',
    'dashboard.activity.items.priceAlert.title': 'Price alert',
    'dashboard.activity.items.keywordAdded.title': 'Keyword added',
    'dashboard.activity.items.videoSource.title': 'Video source',
    'dashboard.activity.items.goalReached.title': 'Goal reached',
};

const translate = (key) => translations[key] ?? key;

describe('buildWorkspaceDashboardData', () => {
    it('builds the dashboard sections without changing module routes', () => {
        const dashboard = buildWorkspaceDashboardData(translate);

        expect(dashboard.stats).toHaveLength(4);
        expect(dashboard.highlights).toHaveLength(3);
        expect(dashboard.modules.map((module) => module.href)).toEqual([
            '/coins',
            '/stocks',
            '/coins/feed-keywords',
            '/video-automation/trending',
        ]);
        expect(dashboard.activity).toHaveLength(4);
    });
});
