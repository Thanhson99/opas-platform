import AppIcon from '../../../components/icons/AppIcon';

/**
 * Render the keyword creation form.
 *
 * @param {{
 *   form: { keyword: string, tags: string },
 *   t: (key: string) => string,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => void,
 *   onFormChange: (nextForm: { keyword: string, tags: string }) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function KeywordFormCard({ form, t, onSubmit, onFormChange }) {
    return (
        <section className="app-form-card app-form-card--accent">
            <h2 className="app-form-card__title">{t('keywordsPage.form.title')}</h2>
            <p className="app-form-card__text">{t('keywordsPage.form.text')}</p>
            <form className="app-form" onSubmit={onSubmit}>
                <div className="app-field">
                    <label className="app-label" htmlFor="keyword-form-keyword">
                        {t('keywordsPage.form.keyword')}
                    </label>
                    <input
                        id="keyword-form-keyword"
                        className="app-input"
                        value={form.keyword}
                        onChange={(event) =>
                            onFormChange({
                                ...form,
                                keyword: event.target.value,
                            })
                        }
                        required
                    />
                </div>
                <div className="app-field">
                    <label className="app-label" htmlFor="keyword-form-tags">
                        {t('keywordsPage.form.tags')}
                    </label>
                    <input
                        id="keyword-form-tags"
                        className="app-input"
                        placeholder={t('keywordsPage.form.tagsPlaceholder')}
                        value={form.tags}
                        onChange={(event) =>
                            onFormChange({
                                ...form,
                                tags: event.target.value,
                            })
                        }
                    />
                </div>
                <button className="app-button app-button--primary" type="submit">
                    <AppIcon name="plus" />
                    {t('keywordsPage.form.submit')}
                </button>
            </form>
        </section>
    );
}
