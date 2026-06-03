import { memo } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render the lightweight inline markdown tokens used by provider setup docs.
 */
function renderInlineRichText(text) {
    const tokens = String(text)
        .split(/(`[^`]+`|\*\*[^*]+\*\*)/g)
        .filter(Boolean);

    return tokens.map((token, index) => {
        if (token.startsWith('**') && token.endsWith('**')) {
            return <strong key={`${token}-${index}`}>{token.slice(2, -2)}</strong>;
        }

        if (token.startsWith('`') && token.endsWith('`')) {
            return <code key={`${token}-${index}`}>{token.slice(1, -1)}</code>;
        }

        return token;
    });
}

/**
 * Render localized provider setup documentation.
 */
function AuthProviderDocsPanel({ docs, t }) {
    return (
        <section className="app-provider-docs">
            <div className="app-provider-docs__head">
                <h3 className="app-form-card__title">{docs.title}</h3>
                <p className="app-form-card__text">{docs.intro}</p>
            </div>

            {docs.links.length > 0 ? (
                <div className="app-provider-docs__links">
                    {docs.links.map((link) => (
                        <a
                            key={link.url}
                            href={link.url}
                            target="_blank"
                            rel="noreferrer"
                            className="app-provider-docs__link"
                            title={link.label}
                        >
                            <span>{link.label}</span>
                            <span className="app-provider-docs__link-arrow">
                                <span>{t('adminAuth.docs.openLink')}</span>
                                <AppIcon name="arrow-right" />
                            </span>
                        </a>
                    ))}
                </div>
            ) : null}

            <div className="app-provider-docs__content">
                {docs.steps.map((step) => (
                    <section key={step.title} className="app-provider-docs__section">
                        <h4 className="app-provider-docs__section-title">{step.title}</h4>
                        <ol className="app-provider-docs__list">
                            {step.items.map((item) => (
                                <li key={item}>{renderInlineRichText(item)}</li>
                            ))}
                        </ol>
                    </section>
                ))}

                {docs.fields.length > 0 ? (
                    <section className="app-provider-docs__section">
                        <h4 className="app-provider-docs__section-title">
                            {t('adminAuth.docs.formHelpTitle')}
                        </h4>
                        <ul className="app-provider-docs__list app-provider-docs__list--plain">
                            {docs.fields.map((field) => (
                                <li key={field.label}>
                                    <strong>{field.label}:</strong>{' '}
                                    {renderInlineRichText(field.text)}
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}
            </div>
        </section>
    );
}

export default memo(AuthProviderDocsPanel);
