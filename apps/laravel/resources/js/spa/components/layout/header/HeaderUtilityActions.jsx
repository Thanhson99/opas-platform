import AppIcon from '../../icons/AppIcon';
import LanguageSelect from '../LanguageSelect';
import HeaderSearch from './HeaderSearch';

/**
 * Render search, alert, and language controls in the workspace header.
 */
export default function HeaderUtilityActions({ t }) {
    return (
        <div className="opas-header__utility">
            <HeaderSearch t={t} />
            <button
                type="button"
                className="opas-header__icon-chip"
                aria-label={t('header.alertsButton')}
            >
                <AppIcon name="alerts" />
            </button>
            <LanguageSelect />
        </div>
    );
}
