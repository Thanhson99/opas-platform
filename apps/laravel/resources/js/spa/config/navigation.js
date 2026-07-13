/**
 * @typedef {{
 *   labelKey: string,
 *   href: string,
 *   icon: string,
 *   activePrefixes?: string[],
 *   adminOnly?: boolean,
 * }} NavigationItem
 */

/**
 * @typedef {{
 *   labelKey: string,
 *   items: NavigationItem[],
 * }} NavigationSection
 */

/**
 * Define the sidebar navigation contract for workspace and admin routes.
 *
 * @type {NavigationSection[]}
 */
export const navigation = [
    {
        labelKey: 'nav.workspace',
        items: [
            {
                labelKey: 'nav.dashboard',
                href: '/',
                icon: 'dashboard',
                activePrefixes: ['/'],
            },
        ],
    },
    {
        labelKey: 'nav.coinManager',
        items: [
            {
                labelKey: 'nav.coins',
                href: '/coins',
                icon: 'coins',
                activePrefixes: ['/coins', '/coins/show/'],
            },
            {
                labelKey: 'nav.priceAlerts',
                href: '/coins/price-alert-settings',
                icon: 'alerts',
                activePrefixes: ['/coins/price-alert-settings', '/coins/price-alert-settings/'],
            },
            {
                labelKey: 'nav.contentKeywords',
                href: '/coins/feed-keywords',
                icon: 'keywords',
                activePrefixes: ['/coins/feed-keywords'],
            },
        ],
    },
    {
        labelKey: 'nav.stockManager',
        items: [
            {
                labelKey: 'nav.stocks',
                href: '/stocks',
                icon: 'stocks',
                activePrefixes: ['/stocks'],
            },
        ],
    },
    {
        labelKey: 'nav.videoAutomation',
        items: [
            {
                labelKey: 'nav.trendingVideos',
                href: '/video-automation/trending',
                icon: 'videos',
                activePrefixes: ['/video-automation/trending'],
            },
            {
                labelKey: 'nav.douyinDashboard',
                href: '/douyin',
                icon: 'workflow',
                activePrefixes: ['/douyin'],
            },
        ],
    },
    {
        labelKey: 'nav.admin',
        items: [
            {
                labelKey: 'adminUsers.menuLabel',
                href: '/admin/users',
                icon: 'users',
                activePrefixes: ['/admin/users'],
                adminOnly: true,
            },
            {
                labelKey: 'nav.authProviders',
                href: '/admin/auth/providers',
                icon: 'shield',
                activePrefixes: ['/admin/auth/providers'],
                adminOnly: true,
            },
        ],
    },
];
