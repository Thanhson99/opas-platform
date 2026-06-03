import EmptyState from '../../../components/ui/EmptyState';
import AppIcon from '../../../components/icons/AppIcon';

/**
 * Render the keyword management list.
 *
 * @param {{
 *   keywords: Array<Record<string, unknown>>,
 *   t: (key: string) => string,
 *   onDelete: (id: number|string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function KeywordListCard({ keywords, t, onDelete }) {
    return (
        <section className="app-form-card">
            <h2 className="app-form-card__title">{t('keywordsPage.list.title')}</h2>
            <p className="app-form-card__text">{t('keywordsPage.list.text')}</p>
            {keywords.length === 0 ? <EmptyState text={t('keywordsPage.list.empty')} /> : null}
            <div className="app-card-stack">
                {keywords.map((item) => (
                    <KeywordListItem
                        item={item}
                        key={item.id}
                        t={t}
                        onDelete={() => onDelete(item.id)}
                    />
                ))}
            </div>
        </section>
    );
}

/**
 * Render one keyword row with tag chips and actions.
 *
 * @param {{
 *   item: Record<string, unknown>,
 *   t: (key: string) => string,
 *   onDelete: () => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function KeywordListItem({ item, t, onDelete }) {
    const tags = item.tags ?? [];

    return (
        <div className="app-list-card">
            <div className="app-list-card__head">
                <div>
                    <strong className="app-list-card__title">{item.keyword}</strong>
                    <div className="app-chip-row">
                        {tags.map((tag) => (
                            <span className="app-chip" key={tag.id}>
                                #{tag.name}
                            </span>
                        ))}
                    </div>
                </div>
                <button
                    type="button"
                    className="app-button app-button--danger"
                    onClick={onDelete}
                    title={`${t('common.delete')} ${item.keyword}`}
                    aria-label={`${t('common.delete')} ${item.keyword}`}
                >
                    <AppIcon name="trash" />
                    {t('common.delete')}
                </button>
            </div>
        </div>
    );
}
