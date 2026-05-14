export default function MetricCard({ label, value, hint, tone = 'sky' }) {
    return (
        <article className={`app-metric app-metric--${tone}`}>
            <p className="app-metric__label">{label}</p>
            <p className="app-metric__value">{value}</p>
            {hint ? <p className="app-metric__hint">{hint}</p> : null}
        </article>
    );
}
