import { memo, useCallback } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render provider save and enablement controls.
 */
function AuthProviderFooterActions({
    form,
    hasUnsavedChanges,
    hasTouchedProvider,
    validationErrors,
    isEmailProvider,
    canSave,
    saving,
    onToggleRequest,
    onSaveRequest,
    t,
}) {
    const handleEnabledChange = useCallback(
        (event) => onToggleRequest(event.target.checked),
        [onToggleRequest],
    );

    return (
        <section className="app-provider-section app-provider-section--footer">
            {hasUnsavedChanges && validationErrors.length > 0 ? (
                <div className="app-provider-note app-provider-note--warning">
                    {t('adminAuth.footerValidationHint')}
                </div>
            ) : hasTouchedProvider && !hasUnsavedChanges ? (
                <div className="app-provider-note app-provider-note--error">
                    {t('adminAuth.noChangesToSave')}
                </div>
            ) : hasUnsavedChanges ? (
                <div className="app-provider-note app-provider-note--success">
                    {t('adminAuth.readyToSave')}
                </div>
            ) : null}

            <div className="app-provider-actions">
                {isEmailProvider ? (
                    <div className="app-provider-lock">
                        <span className="app-provider-lock__badge">
                            <AppIcon name="lock" />
                            {t('adminAuth.emailProvider.fixedBadge')}
                        </span>
                        <p className="app-provider-lock__text">
                            {t('adminAuth.emailProvider.fixedText')}
                        </p>
                    </div>
                ) : (
                    <label className="app-switch">
                        <input
                            type="checkbox"
                            checked={form.enabled}
                            aria-label={
                                form.enabled
                                    ? t('adminAuth.status.enabled')
                                    : t('adminAuth.status.disabled')
                            }
                            onChange={handleEnabledChange}
                        />
                        <span className="app-switch__track">
                            <span className="app-switch__thumb" />
                        </span>
                        <span className="app-switch__text">
                            {form.enabled
                                ? t('adminAuth.status.enabled')
                                : t('adminAuth.status.disabled')}
                        </span>
                    </label>
                )}
                <button
                    type="button"
                    className="app-button app-button--primary"
                    onClick={onSaveRequest}
                    disabled={!canSave}
                    title={t('adminAuth.saveButton')}
                >
                    <AppIcon name="check" />
                    {saving ? t('adminAuth.saving') : t('adminAuth.saveButton')}
                </button>
            </div>
        </section>
    );
}

export default memo(AuthProviderFooterActions);
