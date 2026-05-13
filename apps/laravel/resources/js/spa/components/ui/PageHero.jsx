export default function PageHero({ eyebrow, title, text, actions, aside, children }) {
    return (
        <section className="app-hero">
            <div className="app-hero__main">
                {eyebrow ? <p className="app-hero__eyebrow">{eyebrow}</p> : null}
                <h2 className="app-hero__title">{title}</h2>
                {text ? <p className="app-hero__text">{text}</p> : null}
                {children ? <div className="app-hero__meta">{children}</div> : null}
                {actions ? <div className="app-hero__actions">{actions}</div> : null}
            </div>

            {aside ? <div className="app-hero__aside">{aside}</div> : null}
        </section>
    );
}
