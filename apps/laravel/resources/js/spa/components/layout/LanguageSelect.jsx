import { useLanguage } from '../../features/i18n/context/LanguageContext';

/**
 * Render the shared language switcher for the SPA.
 *
 * @returns {import('react').JSX.Element}
 */
export default function LanguageSelect() {
    const { language, setLanguage, t } = useLanguage();

    return (
        <label className="opas-language">
            <select
                className="opas-language__select"
                value={language}
                onChange={(event) => setLanguage(event.target.value)}
                aria-label={t('common.language')}
            >
                <option value="en">{t('common.english')}</option>
                <option value="vi">{t('common.vietnamese')}</option>
            </select>
        </label>
    );
}
