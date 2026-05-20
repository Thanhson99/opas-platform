import { useLanguage } from '../../features/i18n/context/LanguageContext';

/**
 * Render the shared footer used by workspace and admin shells.
 *
 * @param {{ variant?: 'workspace' | 'admin' }} props
 * @returns {import('react').JSX.Element}
 */
export default function Footer({ variant = 'workspace' }) {
    const { t } = useLanguage();

    return (
        <footer className={`opas-footer opas-footer--${variant}`}>
            <div className="opas-footer__surface">
                <div className="opas-footer__brand">
                    <strong>OPAS</strong>
                    <span>
                        {variant === 'admin' ? t('footer.adminLabel') : t('footer.appLabel')}
                    </span>
                </div>
                <p>{t('footer.text')}</p>
            </div>
        </footer>
    );
}
