/**
 * Render the shared marketing panel used across auth entry screens.
 */
export default function AuthShowcase({ eyebrow, title, text, tags = [], cta, imageSrc }) {
    return (
        <article className="app-auth-showcase">
            <div className="app-auth-showcase__media">
                <div className="app-auth-visual">
                    <img src={imageSrc} alt="OPAS workspace illustration" />
                </div>
            </div>

            <div className="app-auth-showcase__copy">
                <p className="app-auth-showcase__eyebrow">{eyebrow}</p>
                <h2 className="app-auth-showcase__title">{title}</h2>
                <p className="app-auth-showcase__text">{text}</p>
                <div className="app-chip-row">
                    {tags.map((tag) => (
                        <span className="app-chip" key={tag}>
                            {tag}
                        </span>
                    ))}
                </div>
                {cta}
            </div>
        </article>
    );
}
