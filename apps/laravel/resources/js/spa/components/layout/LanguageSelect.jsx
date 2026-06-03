import { memo, useCallback } from 'react';
import { useLanguage } from '../../features/i18n/context/LanguageContext';

/**
 * Render the shared language switcher for the SPA.
 *
 * @returns {import('react').JSX.Element}
 */
function LanguageSelect() {
    const { language, setLanguage, t } = useLanguage();
    const label = t('common.language');

    const handleLanguageChange = useCallback(
        (event) => {
            setLanguage(event.target.value);
        },
        [setLanguage],
    );

    return (
        <label className="opas-language">
            <select
                className="opas-language__select"
                value={language}
                onChange={handleLanguageChange}
                aria-label={label}
                title={label}
            >
                <option value="en">{t('common.english')}</option>
                <option value="vi">{t('common.vietnamese')}</option>
            </select>
        </label>
    );
}

export default memo(LanguageSelect);
