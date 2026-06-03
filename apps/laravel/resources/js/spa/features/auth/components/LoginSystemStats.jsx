const systemStats = [
    { label: 'Pipelines', value: '12', tone: 'cyan' },
    { label: 'Workers', value: '08', tone: 'lime' },
    { label: 'Models', value: '02', tone: 'orange' },
    { label: 'Status', value: 'Online', tone: 'pink' },
];

/**
 * Render decorative system status counters under the login card.
 *
 * @returns {import('react').JSX.Element}
 */
export default function LoginSystemStats() {
    return (
        <div className="app-auth-login-card__stats" aria-hidden="true">
            {systemStats.map((item) => (
                <div
                    className={`app-auth-login-card__stat app-auth-login-card__stat--${item.tone}`}
                    key={item.label}
                >
                    <span>{item.label}</span>
                    <strong>{item.value}</strong>
                </div>
            ))}
        </div>
    );
}
