import { memo } from 'react';
import { useLanguage } from '../../features/i18n/context/LanguageContext';

/**
 * Render a shared loading placeholder with a short status label.
 *
 * @param {{ text?: string }} props
 * @returns {import('react').JSX.Element}
 */
function LoadingState({ text = '' }) {
    const { t } = useLanguage();
    const label = text || t('common.loading');

    return (
        <div className="app-feedback app-feedback--loading" role="status" aria-live="polite">
            <div className="app-feedback__pulse" aria-hidden="true" />
            <p>{label}</p>
        </div>
    );
}

export default memo(LoadingState);
