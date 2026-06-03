import { memo } from 'react';
import ErrorState from '../../../../components/ui/ErrorState';

function getFlashClassName(type) {
    if (type === 'success') {
        return 'app-provider-note app-provider-note--success';
    }

    if (type === 'info') {
        return 'app-provider-note app-provider-note--info';
    }

    return 'app-provider-note app-provider-note--error';
}

/**
 * Render page-level provider admin errors, flash messages, and safety warnings.
 */
function AuthProviderAdminAlerts({
    error,
    flash,
    provider,
    providerServerErrors,
    isLastActiveProvider,
    t,
}) {
    return (
        <>
            {error ? <ErrorState text={error} /> : null}

            {flash ? (
                <div className={getFlashClassName(flash.type)} role="status">
                    {flash.message}
                </div>
            ) : null}

            {provider.issues?.length ? (
                <div className="app-provider-note" role="status">
                    <strong>{t('adminAuth.currentStatus')}</strong> {provider.issues.join(' ')}
                </div>
            ) : null}

            {providerServerErrors.enabled?.[0] ? (
                <div className="app-provider-note app-provider-note--error" role="alert">
                    {providerServerErrors.enabled[0]}
                </div>
            ) : null}

            {isLastActiveProvider ? (
                <div className="app-provider-note" role="status">
                    <strong>{t('adminAuth.lastProviderTitle')}</strong>{' '}
                    {t('adminAuth.lastProviderText')}
                </div>
            ) : null}
        </>
    );
}

export default memo(AuthProviderAdminAlerts);
