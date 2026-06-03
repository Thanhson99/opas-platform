/**
 * Register locale loaders without forcing every language into the startup bundle.
 */
const localeLoaders = {
    en: () => import('./messages/en').then((module) => module.enMessages),
    vi: () => import('./messages/vi').then((module) => module.viMessages),
};

export const defaultLocale = 'en';
export const supportedLocales = Object.freeze(Object.keys(localeLoaders));

/**
 * Check whether one locale can be loaded by the SPA.
 *
 * @param {string | null | undefined} locale
 * @returns {boolean}
 */
export function isSupportedLocale(locale) {
    return (
        typeof locale === 'string' && Object.prototype.hasOwnProperty.call(localeLoaders, locale)
    );
}

/**
 * Load one locale catalog.
 *
 * @param {string} locale
 * @returns {Promise<Record<string, unknown>>}
 */
export function loadLocaleMessages(locale) {
    const loader = localeLoaders[locale] ?? localeLoaders[defaultLocale];

    return loader();
}
