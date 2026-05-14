import { useLanguage } from '../../features/i18n/context/LanguageContext';

export default function Footer() {
    const { t } = useLanguage();

    return (
        <footer className="opas-footer">
            <p>
                OPAS <span>{t('footer.text')}</span>
            </p>
        </footer>
    );
}
