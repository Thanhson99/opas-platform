import { getProviderSummary } from './authProviderAdminForm.helpers';

/**
 * Resolve the status pill tone for one provider.
 */
export function getProviderStatusTone(provider) {
    if (provider.active) {
        return 'app-status-pill--success';
    }

    if (provider.ready) {
        return 'app-status-pill--muted';
    }

    return 'app-status-pill--warning';
}

/**
 * Resolve the localized provider visibility label.
 */
export function getProviderVisibilityLabel(form, t) {
    if (form.visibility === 'public') {
        return t('adminAuth.visibility.public');
    }

    if (form.visibility === 'hidden') {
        return t('adminAuth.visibility.hidden');
    }

    return t('adminAuth.visibility.adminOnly');
}

/**
 * Build the derived UI state used by the auth-provider admin page.
 */
export function buildAuthProviderAdminViewModel(provider, form, t) {
    return {
        statusTone: getProviderStatusTone(provider),
        visibilityLabel: getProviderVisibilityLabel(form, t),
        summary: getProviderSummary(provider, t),
    };
}
