const baseProps = {
    fill: 'none',
    viewBox: '0 0 24 24',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': 'true',
};

function DashboardIcon() {
    return (
        <svg {...baseProps}>
            <path d="M4 5.5h7v5H4z" />
            <path d="M13 5.5h7v8h-7z" />
            <path d="M4 12.5h7V20H4z" />
            <path d="M13 15.5h7V20h-7z" />
        </svg>
    );
}

function CoinsIcon() {
    return (
        <svg {...baseProps}>
            <ellipse cx="12" cy="7" rx="6.5" ry="2.5" />
            <path d="M5.5 7v5c0 1.4 2.9 2.5 6.5 2.5s6.5-1.1 6.5-2.5V7" />
            <path d="M5.5 12v5c0 1.4 2.9 2.5 6.5 2.5s6.5-1.1 6.5-2.5v-5" />
        </svg>
    );
}

function BellIcon() {
    return (
        <svg {...baseProps}>
            <path d="M9.5 18h5" />
            <path d="M6.5 15.5h11l-1.7-2.1a3.6 3.6 0 0 1-.8-2.3V9a3 3 0 1 0-6 0v2.1c0 .8-.3 1.6-.8 2.3z" />
        </svg>
    );
}

function TagsIcon() {
    return (
        <svg {...baseProps}>
            <path d="M11 4H5.5A1.5 1.5 0 0 0 4 5.5V11l7.5 7.5a1.4 1.4 0 0 0 2 0l5-5a1.4 1.4 0 0 0 0-2z" />
            <circle cx="7.25" cy="7.25" r="0.75" fill="currentColor" stroke="none" />
        </svg>
    );
}

function ChartIcon() {
    return (
        <svg {...baseProps}>
            <path d="M4 19.5h16" />
            <path d="M6.5 16l4-4 3 2.5 4-5" />
            <path d="M16.5 9.5h1.5V11" />
        </svg>
    );
}

function VideoIcon() {
    return (
        <svg {...baseProps}>
            <rect x="3.5" y="6.5" width="11" height="11" rx="2" />
            <path d="m14.5 10 5-2.5v9L14.5 14" />
        </svg>
    );
}

function MenuIcon() {
    return (
        <svg {...baseProps}>
            <path d="M5 7h14" />
            <path d="M5 12h14" />
            <path d="M5 17h14" />
        </svg>
    );
}

function HeartIcon({ filled = false }) {
    if (filled) {
        return (
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                <path d="M12 20.3 4.9 13.7a4.8 4.8 0 0 1 6.8-6.8L12 7.2l.3-.3a4.8 4.8 0 1 1 6.8 6.8z" />
            </svg>
        );
    }

    return (
        <svg {...baseProps}>
            <path d="M12 20.3 4.9 13.7a4.8 4.8 0 0 1 6.8-6.8L12 7.2l.3-.3a4.8 4.8 0 1 1 6.8 6.8z" />
        </svg>
    );
}

const icons = {
    dashboard: DashboardIcon,
    coins: CoinsIcon,
    alerts: BellIcon,
    keywords: TagsIcon,
    stocks: ChartIcon,
    videos: VideoIcon,
    menu: MenuIcon,
    heart: HeartIcon,
};

export default function AppIcon({ name, filled = false, className = '' }) {
    const Icon = icons[name];

    if (!Icon) {
        return null;
    }

    return (
        <span className={`app-icon ${className}`.trim()}>
            <Icon filled={filled} />
        </span>
    );
}
