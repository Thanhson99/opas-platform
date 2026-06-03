import { getNonFormProviders } from '../lib/publicAuthProviders';

/**
 * @typedef {import('../lib/publicAuthProviders').PublicAuthProvider} PublicAuthProvider
 */

/**
 * @typedef {{
 *   items: PublicAuthProvider[],
 *   loading: boolean,
 *   error: string | null,
 *   hasLoginProviders: boolean,
 * }} LoginProvidersViewModel
 */

/**
 * @typedef {{ providers: PublicAuthProvider[] }} LoginRegistrationViewModel
 */

/**
 * Build the provider section contract for the login UI.
 *
 * @param {{
 *   loginProviders: PublicAuthProvider[],
 *   emailProvider: PublicAuthProvider | null,
 *   providersLoading: boolean,
 *   providersError: string | null,
 * }} options
 * @returns {LoginProvidersViewModel}
 */
export function buildLoginProvidersViewModel({
    loginProviders,
    emailProvider,
    providersLoading,
    providersError,
}) {
    return {
        items: getNonFormProviders(loginProviders, emailProvider),
        loading: providersLoading,
        error: providersError,
        hasLoginProviders: loginProviders.length > 0,
    };
}

/**
 * Build the registration availability contract.
 *
 * @param {PublicAuthProvider[]} registerProviders
 * @returns {LoginRegistrationViewModel}
 */
export function buildLoginRegistrationViewModel(registerProviders) {
    return {
        providers: registerProviders,
    };
}
