import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { messages } from '../messages';

const STORAGE_KEY = 'opas.language';
const LanguageContext = createContext(null);

function resolveMessage(locale, path) {
    return path.split('.').reduce((value, key) => value?.[key], messages[locale]);
}

export function LanguageProvider({ children }) {
    const [language, setLanguage] = useState(() => {
        if (typeof window === 'undefined') {
            return 'en';
        }

        return localStorage.getItem(STORAGE_KEY) || 'en';
    });

    useEffect(() => {
        localStorage.setItem(STORAGE_KEY, language);
        document.documentElement.lang = language;
    }, [language]);

    const value = useMemo(
        () => ({
            language,
            setLanguage,
            t: (path) => resolveMessage(language, path) ?? resolveMessage('en', path) ?? path,
        }),
        [language],
    );

    return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>;
}

export function useLanguage() {
    const context = useContext(LanguageContext);

    if (!context) {
        throw new Error('useLanguage must be used within LanguageProvider');
    }

    return context;
}
