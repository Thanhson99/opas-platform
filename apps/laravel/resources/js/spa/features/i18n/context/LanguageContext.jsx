import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { messages } from '../messages';

const STORAGE_KEY = 'opas.language';
/**
 * @typedef {{
 *   language: string,
 *   setLanguage: import('react').Dispatch<import('react').SetStateAction<string>>,
 *   t: (path: string) => string,
 * }} LanguageContextValue
 */

/** @type {import('react').Context<LanguageContextValue | null>} */
const LanguageContext = createContext(null);

/**
 * Resolve one nested translation path from the locale message tree.
 *
 * @param {string} locale
 * @param {string} path
 * @returns {unknown}
 */
function resolveMessage(locale, path) {
    return path.split('.').reduce((value, key) => value?.[key], messages[locale]);
}

/**
 * Provide the active language and translation helper to the SPA.
 *
 * @param {{ children: import('react').ReactNode }} props
 * @returns {import('react').JSX.Element}
 */
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

/**
 * Read the shared language context and fail fast when it is missing.
 *
 * @returns {LanguageContextValue}
 */
export function useLanguage() {
    const context = useContext(LanguageContext);

    if (!context) {
        throw new Error('useLanguage must be used within LanguageProvider');
    }

    return context;
}
