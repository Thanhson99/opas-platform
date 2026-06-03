import { useEffect, useState } from 'react';

const SHOWCASE_QUERY = '(min-width: 576px)';
const SHOWCASE_STATS = [
    { label: 'Pipelines', value: '12', tone: 'cyan' },
    { label: 'Workers', value: '08', tone: 'lime' },
    { label: 'Status', value: 'Online', tone: 'pink' },
];

function useShowcaseViewport() {
    const [showcaseVisible, setShowcaseVisible] = useState(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return true;
        }

        return window.matchMedia(SHOWCASE_QUERY).matches;
    });

    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return undefined;
        }

        const mediaQuery = window.matchMedia(SHOWCASE_QUERY);
        const handleChange = () => setShowcaseVisible(mediaQuery.matches);

        handleChange();
        mediaQuery.addEventListener('change', handleChange);

        return () => mediaQuery.removeEventListener('change', handleChange);
    }, []);

    return showcaseVisible;
}

/**
 * Render the shared cyber showcase panel used across auth entry screens.
 *
 * @param {{
 *   eyebrow: string,
 *   title: string,
 *   text: string,
 *   tags?: Array<string>,
 *   cta?: import('react').ReactNode,
 *   imageSrc: string,
 * }} props
 * @returns {import('react').JSX.Element | null}
 */
export default function AuthShowcase({ eyebrow, title, text, tags = [], cta, imageSrc }) {
    const showcaseVisible = useShowcaseViewport();

    if (!showcaseVisible) {
        return null;
    }

    return (
        <article className="app-auth-showcase">
            <AuthShowcaseStatus />
            <div className="app-auth-showcase__media">
                <div className="app-auth-visual">
                    <div className="app-auth-visual__stage">
                        <img
                            src={imageSrc}
                            alt="OPAS workspace illustration"
                            decoding="async"
                            loading="lazy"
                        />
                        <div className="app-auth-visual__overlay" aria-hidden="true">
                            <span>n8n</span>
                            <span>Laravel</span>
                            <span>OPAS</span>
                        </div>
                    </div>
                    <AuthShowcaseConsole />
                </div>
            </div>

            <div className="app-auth-showcase__copy">
                <p className="app-auth-showcase__eyebrow">{eyebrow}</p>
                <h2 className="app-auth-showcase__title">{title}</h2>
                <p className="app-auth-showcase__text">{text}</p>
                <AuthShowcaseTags tags={tags} />
                {cta}
            </div>
        </article>
    );
}

/**
 * Render the system console overlay inside the auth showcase image.
 *
 * @returns {import('react').JSX.Element}
 */
function AuthShowcaseConsole() {
    return (
        <div className="app-auth-visual__console" aria-hidden="true">
            <div className="app-auth-visual__console-head">
                <span>OPAS NODE</span>
                <strong>LIVE</strong>
            </div>
            <div className="app-auth-visual__console-grid">
                {SHOWCASE_STATS.map((stat) => (
                    <span
                        className={`app-auth-visual__console-stat app-auth-visual__console-stat--${stat.tone}`}
                        key={stat.label}
                    >
                        <small>{stat.label}</small>
                        <strong>{stat.value}</strong>
                    </span>
                ))}
            </div>
        </div>
    );
}

/**
 * Render decorative auth system status metadata.
 *
 * @returns {import('react').JSX.Element}
 */
function AuthShowcaseStatus() {
    return (
        <div className="app-auth-showcase__status" aria-hidden="true">
            <span>OPAS // ONLINE</span>
            <span>FLOW SECURE</span>
            <span>NODE 07</span>
        </div>
    );
}

/**
 * Render showcase tags in the shared auth panel.
 *
 * @param {{ tags: Array<string> }} props
 * @returns {import('react').JSX.Element | null}
 */
function AuthShowcaseTags({ tags }) {
    if (tags.length === 0) {
        return null;
    }

    return (
        <div className="app-auth-showcase__tags">
            {tags.map((tag) => (
                <span className="app-auth-showcase__tag" key={tag}>
                    {tag}
                </span>
            ))}
        </div>
    );
}
