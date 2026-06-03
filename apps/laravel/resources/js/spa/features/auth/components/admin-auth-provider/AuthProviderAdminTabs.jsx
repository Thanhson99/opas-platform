import { memo, useCallback } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render the auth-provider admin detail tab selector.
 */
function AuthProviderAdminTabs({ activeTab, onChange, t }) {
    const handleTabClick = useCallback(
        (event) => {
            onChange(event.currentTarget.dataset.tab);
        },
        [onChange],
    );

    return (
        <div
            className="app-provider-inline-tabs"
            role="tablist"
            aria-label={t('adminAuth.tabs.label')}
        >
            <button
                type="button"
                role="tab"
                data-tab="config"
                aria-selected={activeTab === 'config'}
                className={`app-provider-inline-tab ${activeTab === 'config' ? 'is-active' : ''}`}
                onClick={handleTabClick}
                title={t('adminAuth.tabs.setup')}
            >
                <AppIcon name="settings" />
                {t('adminAuth.tabs.setup')}
            </button>
            <button
                type="button"
                role="tab"
                data-tab="docs"
                aria-selected={activeTab === 'docs'}
                className={`app-provider-inline-tab ${activeTab === 'docs' ? 'is-active' : ''}`}
                onClick={handleTabClick}
                title={t('adminAuth.tabs.guide')}
            >
                <AppIcon name="info" />
                {t('adminAuth.tabs.guide')}
            </button>
        </div>
    );
}

export default memo(AuthProviderAdminTabs);
