import AppIcon from '../../../components/icons/AppIcon';

/**
 * Render the coin alert edit form.
 *
 * @param {{
 *   form: Record<string, unknown>,
 *   t: (key: string) => string,
 *   onBack: () => void,
 *   onFormChange: (nextForm: Record<string, unknown>) => void,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function AlertEditFormCard({ form, t, onBack, onFormChange, onSubmit }) {
    return (
        <section className="app-form-card app-form-card--accent">
            <h2 className="app-form-card__title">{t('alertEditPage.form.title')}</h2>
            <p className="app-form-card__text">{t('alertEditPage.form.text')}</p>
            <form className="app-form" onSubmit={onSubmit}>
                <AlertThresholdField form={form} t={t} onFormChange={onFormChange} />
                <AlertTypeField form={form} t={t} onFormChange={onFormChange} />
                <AlertDirectionField form={form} t={t} onFormChange={onFormChange} />
                <AlertStatusField form={form} t={t} onFormChange={onFormChange} />
                <div className="app-action-row">
                    <button
                        className="app-button app-button--primary"
                        type="submit"
                        title={t('alertEditPage.actions.save')}
                    >
                        <AppIcon name="check" />
                        {t('alertEditPage.actions.save')}
                    </button>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={onBack}
                        title={t('alertEditPage.actions.back')}
                    >
                        <AppIcon name="arrow-right" className="app-action-row__back-icon" />
                        {t('alertEditPage.actions.back')}
                    </button>
                </div>
            </form>
        </section>
    );
}

function AlertThresholdField({ form, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="alert-threshold-percent">
                {t('alertEditPage.form.threshold')}
            </label>
            <input
                id="alert-threshold-percent"
                className="app-input"
                type="number"
                step="0.01"
                value={form.threshold_percent ?? ''}
                onChange={(event) =>
                    onFormChange({
                        ...form,
                        threshold_percent: event.target.value,
                    })
                }
            />
        </div>
    );
}

function AlertTypeField({ form, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="alert-type">
                {t('alertEditPage.form.type')}
            </label>
            <select
                id="alert-type"
                className="app-input"
                value={form.type}
                onChange={(event) => onFormChange({ ...form, type: event.target.value })}
            >
                <option value="preset">{t('alertEditPage.options.preset')}</option>
                <option value="custom">{t('alertEditPage.options.custom')}</option>
            </select>
        </div>
    );
}

function AlertDirectionField({ form, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="alert-direction">
                {t('alertEditPage.form.direction')}
            </label>
            <select
                id="alert-direction"
                className="app-input"
                value={form.direction ?? ''}
                onChange={(event) =>
                    onFormChange({
                        ...form,
                        direction: event.target.value || null,
                    })
                }
            >
                <option value="">{t('common.none')}</option>
                <option value="increase">{t('alertEditPage.options.increase')}</option>
                <option value="decrease">{t('alertEditPage.options.decrease')}</option>
            </select>
        </div>
    );
}

function AlertStatusField({ form, t, onFormChange }) {
    return (
        <div className="app-field">
            <label className="app-label" htmlFor="alert-status">
                {t('alertEditPage.form.status')}
            </label>
            <select
                id="alert-status"
                className="app-input"
                value={form.is_active ? '1' : '0'}
                onChange={(event) =>
                    onFormChange({
                        ...form,
                        is_active: event.target.value === '1',
                    })
                }
            >
                <option value="1">{t('alertsPage.status.active')}</option>
                <option value="0">{t('alertsPage.status.inactive')}</option>
            </select>
        </div>
    );
}
