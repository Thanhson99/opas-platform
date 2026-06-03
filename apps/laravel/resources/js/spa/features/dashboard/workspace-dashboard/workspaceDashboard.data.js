/**
 * Build dashboard metric definitions.
 *
 * @param {(key: string) => string} t
 * @returns {Array<{label: string, note?: string, value: string, delta: string, deltaText: string, icon: string, tone: string, deltaIcon?: string}>}
 */
function buildDashboardStats(t) {
    return [
        {
            label: t('dashboard.stats.totalProfit.label'),
            note: t('dashboard.stats.totalProfit.note'),
            value: '$128,450.75',
            delta: '12.5%',
            deltaText: t('dashboard.stats.totalProfit.deltaText'),
            icon: 'dollar',
            tone: 'blue',
        },
        {
            label: t('dashboard.stats.dailyProfit.label'),
            value: '$4,590.20',
            delta: '8.3%',
            deltaText: t('dashboard.stats.dailyProfit.deltaText'),
            icon: 'analytics',
            tone: 'amber',
        },
        {
            label: t('dashboard.stats.activeAutomations.label'),
            value: '128',
            delta: '6',
            deltaText: t('dashboard.stats.activeAutomations.deltaText'),
            icon: 'zap',
            tone: 'sky',
            deltaIcon: 'arrow-up',
        },
        {
            label: t('dashboard.stats.savedTime.label'),
            value: '128h',
            delta: '16.2%',
            deltaText: t('dashboard.stats.savedTime.deltaText'),
            icon: 'clock',
            tone: 'gold',
        },
    ];
}

/**
 * Build dashboard highlight definitions.
 *
 * @param {(key: string) => string} t
 * @returns {Array<{title: string, text: string, icon: string, tone: string}>}
 */
function buildDashboardHighlights(t) {
    return [
        {
            title: t('dashboard.highlights.workflow.title'),
            text: t('dashboard.highlights.workflow.text'),
            icon: 'workflow',
            tone: 'blue',
        },
        {
            title: t('dashboard.highlights.analytics.title'),
            text: t('dashboard.highlights.analytics.text'),
            icon: 'analytics',
            tone: 'sky',
        },
        {
            title: t('dashboard.highlights.optimization.title'),
            text: t('dashboard.highlights.optimization.text'),
            icon: 'target',
            tone: 'green',
        },
    ];
}

/**
 * Build dashboard module shortcut definitions.
 *
 * @param {(key: string) => string} t
 * @returns {Array<{title: string, text: string, href: string, icon: string, tone: string}>}
 */
function buildDashboardModules(t) {
    return [
        {
            title: t('nav.coins'),
            text: t('dashboard.coinsText'),
            href: '/coins',
            icon: 'coins',
            tone: 'blue',
        },
        {
            title: t('nav.stocks'),
            text: t('dashboard.stocksText'),
            href: '/stocks',
            icon: 'stocks',
            tone: 'green',
        },
        {
            title: t('nav.contentKeywords'),
            text: t('dashboard.keywordsText'),
            href: '/coins/feed-keywords',
            icon: 'keywords',
            tone: 'purple',
        },
        {
            title: t('nav.trendingVideos'),
            text: t('dashboard.videosText'),
            href: '/video-automation/trending',
            icon: 'videos',
            tone: 'red',
        },
    ];
}

/**
 * Build dashboard activity definitions.
 *
 * @param {(key: string) => string} t
 * @returns {Array<{title: string, emphasis?: string, timestamp: string, icon: string, tone: string}>}
 */
function buildDashboardActivity(t) {
    return [
        {
            title: t('dashboard.activity.items.priceAlert.title'),
            emphasis: '"Price Alert BTC"',
            timestamp: '23/05/2024 14:32',
            icon: 'check',
            tone: 'green',
        },
        {
            title: t('dashboard.activity.items.keywordAdded.title'),
            emphasis: '"AI Crypto"',
            timestamp: '23/05/2024 14:20',
            icon: 'plus',
            tone: 'purple',
        },
        {
            title: t('dashboard.activity.items.videoSource.title'),
            emphasis: '"Crypto News"',
            timestamp: '23/05/2024 13:58',
            icon: 'play',
            tone: 'red',
        },
        {
            title: t('dashboard.activity.items.goalReached.title'),
            timestamp: '23/05/2024 13:40',
            icon: 'trophy',
            tone: 'amber',
        },
    ];
}

/**
 * Build the static workspace dashboard data contract from translated labels.
 *
 * @param {(key: string) => string} t
 * @returns {{
 *   stats: Array<{label: string, note?: string, value: string, delta: string, deltaText: string, icon: string, tone: string, deltaIcon?: string}>,
 *   highlights: Array<{title: string, text: string, icon: string, tone: string}>,
 *   modules: Array<{title: string, text: string, href: string, icon: string, tone: string}>,
 *   activity: Array<{title: string, emphasis?: string, timestamp: string, icon: string, tone: string}>,
 * }}
 */
export function buildWorkspaceDashboardData(t) {
    return {
        stats: buildDashboardStats(t),
        highlights: buildDashboardHighlights(t),
        modules: buildDashboardModules(t),
        activity: buildDashboardActivity(t),
    };
}
