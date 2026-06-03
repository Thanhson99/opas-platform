import AppIcon from '../../icons/AppIcon';

/**
 * Render the workspace global search input.
 */
export default function HeaderSearch({ t }) {
    return (
        <label className="opas-header__search">
            <AppIcon name="search" />
            <input
                id="workspace-global-search"
                type="search"
                aria-label={t('header.searchLabel')}
                placeholder={t('header.searchPlaceholder')}
                className="opas-header__search-input"
            />
        </label>
    );
}
