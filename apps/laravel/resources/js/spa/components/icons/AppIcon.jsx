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

function ShieldIcon() {
    return (
        <svg {...baseProps}>
            <path d="M12 3.5 5.5 6v5.8c0 4 2.7 6.6 6.5 8.7 3.8-2.1 6.5-4.7 6.5-8.7V6z" />
            <path d="M9.5 12.5 11.3 14l3.2-3.5" />
        </svg>
    );
}

function MailIcon() {
    return (
        <svg {...baseProps}>
            <rect x="3.5" y="6.5" width="17" height="11" rx="2.5" />
            <path d="m5.5 8 6.5 5 6.5-5" />
        </svg>
    );
}

function GoogleIcon() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
            <path
                d="M21.6 12.23c0-.64-.06-1.25-.17-1.83H12v3.47h5.39a4.61 4.61 0 0 1-2 3.03v2.52h3.24c1.9-1.75 2.97-4.34 2.97-7.19Z"
                fill="#4285F4"
            />
            <path
                d="M12 22c2.7 0 4.97-.9 6.63-2.43l-3.24-2.52c-.9.6-2.05.96-3.39.96-2.6 0-4.8-1.76-5.58-4.12H3.08v2.6A9.99 9.99 0 0 0 12 22Z"
                fill="#34A853"
            />
            <path
                d="M6.42 13.89A5.98 5.98 0 0 1 6.1 12c0-.66.12-1.3.32-1.89V7.51H3.08A9.99 9.99 0 0 0 2 12c0 1.61.38 3.14 1.08 4.49l3.34-2.6Z"
                fill="#FBBC05"
            />
            <path
                d="M12 5.98c1.47 0 2.8.5 3.84 1.48l2.88-2.88C16.96 2.94 14.7 2 12 2A9.99 9.99 0 0 0 3.08 7.51l3.34 2.6C7.2 7.74 9.4 5.98 12 5.98Z"
                fill="#EA4335"
            />
        </svg>
    );
}

function GithubIcon() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
            <rect x="2.5" y="2.5" width="19" height="19" rx="9.5" fill="#0D1117" />
            <path
                d="M12 6.2c-3.2 0-5.8 2.58-5.8 5.77 0 2.55 1.66 4.7 3.97 5.47.3.05.4-.13.4-.3v-1.1c-1.62.35-1.96-.68-1.96-.68-.27-.67-.65-.84-.65-.84-.53-.36.04-.35.04-.35.59.04.9.6.9.6.52.91 1.38.65 1.71.5.05-.39.21-.65.37-.79-1.29-.15-2.65-.65-2.65-2.89 0-.64.22-1.17.58-1.58-.05-.15-.26-.74.06-1.55 0 0 .48-.16 1.58.6a5.5 5.5 0 0 1 2.88 0c1.1-.76 1.58-.6 1.58-.6.32.81.11 1.4.06 1.55.36.41.58.94.58 1.58 0 2.25-1.36 2.74-2.66 2.89.21.18.39.52.39 1.05v1.56c0 .17.1.35.4.29A5.8 5.8 0 0 0 17.8 12c0-3.19-2.6-5.77-5.8-5.77Z"
                fill="#ffffff"
            />
        </svg>
    );
}

function FacebookIcon() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
            <rect x="2.5" y="2.5" width="19" height="19" rx="9.5" fill="#1877F2" />
            <path
                d="M13.46 19v-5.88h1.97l.3-2.3h-2.27V9.34c0-.66.18-1.11 1.12-1.11h1.2V6.17c-.21-.03-.92-.09-1.74-.09-1.72 0-2.9 1.04-2.9 2.96v1.78H9.2v2.3h1.94V19h2.32Z"
                fill="#ffffff"
            />
        </svg>
    );
}

function ChevronDownIcon() {
    return (
        <svg {...baseProps}>
            <path d="m7 10 5 5 5-5" />
        </svg>
    );
}

function EyeIcon() {
    return (
        <svg {...baseProps}>
            <path d="M2.8 12s3.2-5.2 9.2-5.2 9.2 5.2 9.2 5.2-3.2 5.2-9.2 5.2S2.8 12 2.8 12Z" />
            <circle cx="12" cy="12" r="2.5" />
        </svg>
    );
}

function EyeOffIcon() {
    return (
        <svg {...baseProps}>
            <path d="M3.5 3.5 20.5 20.5" />
            <path d="M10.4 6.9a9.8 9.8 0 0 1 1.6-.1c6 0 9.2 5.2 9.2 5.2a16.6 16.6 0 0 1-3.5 3.9" />
            <path d="M8.1 8A16.6 16.6 0 0 0 2.8 12s3.2 5.2 9.2 5.2a10 10 0 0 0 3-.4" />
            <path d="M10.7 10.7a2.5 2.5 0 0 0 3.5 3.5" />
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

function UsersIcon() {
    return (
        <svg {...baseProps}>
            <path d="M8 11a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
            <path d="M16.5 10.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
            <path d="M3.8 18.2c.6-2.2 2.5-3.7 4.7-3.7s4.1 1.5 4.7 3.7" />
            <path d="M13.7 17.2c.4-1.6 1.8-2.7 3.4-2.7 1.2 0 2.3.6 3 1.6" />
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
    shield: ShieldIcon,
    mail: MailIcon,
    google: GoogleIcon,
    github: GithubIcon,
    facebook: FacebookIcon,
    'chevron-down': ChevronDownIcon,
    eye: EyeIcon,
    'eye-off': EyeOffIcon,
    heart: HeartIcon,
    users: UsersIcon,
};

export const availableAppIcons = Object.freeze(Object.keys(icons));

export function hasAppIcon(name) {
    return typeof name === 'string' && Object.prototype.hasOwnProperty.call(icons, name);
}

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
