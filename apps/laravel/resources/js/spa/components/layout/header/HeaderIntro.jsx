import AppIcon from '../../icons/AppIcon';

/**
 * Render the workspace header title and sidebar toggle.
 */
export default function HeaderIntro({ title, sidebarOpen = false, onToggleSidebar, t }) {
    return (
        <div className="opas-header__intro-wrap">
            <button
                type="button"
                className={`opas-header__toggle ${sidebarOpen ? 'is-active' : ''}`}
                id="sidebar-toggle"
                aria-label={t('header.toggleNavigation')}
                aria-pressed={sidebarOpen}
                aria-controls="app-sidebar"
                onClick={onToggleSidebar}
                title={t('header.toggleNavigation')}
            >
                <AppIcon name="menu" />
            </button>

            <div className="opas-header__intro">
                <p className="opas-header__eyebrow">{t('header.workspaceEyebrow')}</p>
                <h1 className="opas-header__title">{title}</h1>
            </div>
        </div>
    );
}
