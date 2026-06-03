import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { defaultLocale, isSupportedLocale, loadLocaleMessages } from '../messages';

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
 * @param {Record<string, unknown>} locale
 * @param {string} path
 * @returns {unknown}
 */
function resolveMessage(locale, path) {
    return path.split('.').reduce((value, key) => value?.[key], locale);
}

/**
 * Resolve the stored locale to a supported language.
 *
 * @returns {string}
 */
function resolveInitialLanguage() {
    if (typeof window === 'undefined') {
        return defaultLocale;
    }

    const storedLanguage = localStorage.getItem(STORAGE_KEY);

    return isSupportedLocale(storedLanguage) ? storedLanguage : defaultLocale;
}

/**
 * Provide the active language and translation helper to the SPA.
 *
 * @param {{ children: import('react').ReactNode }} props
 * @returns {import('react').JSX.Element}
 */
export function LanguageProvider({ children }) {
    const [language, setLanguage] = useState(resolveInitialLanguage);
    const [localeMessages, setLocaleMessages] = useState({});

    useEffect(() => {
        localStorage.setItem(STORAGE_KEY, language);
        document.documentElement.lang = language;
    }, [language]);

    useEffect(() => {
        if (localeMessages[language]) {
            return undefined;
        }

        let isActive = true;

        loadLocaleMessages(language)
            .then((messages) => {
                if (!isActive) {
                    return;
                }

                setLocaleMessages((currentMessages) => ({
                    ...currentMessages,
                    [language]: messages,
                }));
            })
            .catch(() => {
                if (isActive) {
                    setLanguage((currentLanguage) =>
                        currentLanguage === defaultLocale ? currentLanguage : defaultLocale,
                    );
                }
            });

        return () => {
            isActive = false;
        };
    }, [language, localeMessages]);

    const activeMessages = useMemo(
        () => localeMessages[language] ?? localeMessages[defaultLocale] ?? {},
        [language, localeMessages],
    );
    const updateLanguage = useCallback((nextLanguage) => {
        setLanguage((currentLanguage) => {
            const resolvedLanguage =
                typeof nextLanguage === 'function' ? nextLanguage(currentLanguage) : nextLanguage;

            return isSupportedLocale(resolvedLanguage) ? resolvedLanguage : defaultLocale;
        });
    }, []);
    const t = useCallback((path) => resolveMessage(activeMessages, path) ?? path, [activeMessages]);

    const value = useMemo(
        () => ({
            language,
            setLanguage: updateLanguage,
            t,
        }),
        [language, t, updateLanguage],
    );

    if (!localeMessages[language]) {
        return (
            <div className="app-feedback app-feedback--loading">
                <span className="app-feedback__pulse" />
                <p>Loading...</p>
            </div>
        );
    }

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
