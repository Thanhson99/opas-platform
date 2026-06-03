import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api from '../../../lib/api';

/**
 * @typedef {{
 *   key: string,
 *   display_name?: string,
 *   type?: string,
 *   icon?: string | null,
 *   capabilities?: Record<string, boolean>,
 *   metadata?: Record<string, unknown>,
 * }} AuthRuntimeProvider
 */

/**
 * @typedef {{
 *   key: string,
 *   display_name?: string,
 *   icon?: string | null,
 * }} AuthLinkedProvider
 */

/**
 * @typedef {{
 *   id?: number,
 *   name?: string,
 *   email?: string,
 *   role?: string,
 *   role_label?: string,
 *   linked_providers?: AuthLinkedProvider[],
 *   current_sign_in_provider?: AuthLinkedProvider | null,
 * }} AuthUser
 */

/**
 * @typedef {{
 *   user: AuthUser | null,
 *   loading: boolean,
 *   authProviders: AuthRuntimeProvider[],
 *   providersLoading: boolean,
 *   providersError: string | null,
 *   isAuthenticated: boolean,
 *   hasEmailLogin: boolean,
 *   loginProviders: AuthRuntimeProvider[],
 *   registerProviders: AuthRuntimeProvider[],
 *   linkableProviders: AuthRuntimeProvider[],
 *   login: (payload: Record<string, unknown>) => Promise<any>,
 *   register: (payload: Record<string, unknown>) => Promise<any>,
 *   logout: () => Promise<void>,
 *   refreshUser: () => Promise<void>,
 *   refreshAuthProviders: (options?: { force?: boolean }) => Promise<void>,
 * }} AuthContextValue
 */

/** @type {import('react').Context<AuthContextValue | null>} */
const AuthContext = createContext(null);
const AUTH_PROVIDER_IMMEDIATE_PATHS = ['/login', '/register', '/account'];
const PUBLIC_AUTH_PATHS = [
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/verify-email',
];
let cachedAuthProviders = null;
let pendingAuthProvidersRequest = null;
const AUTH_PROVIDER_CACHE_ENABLED = import.meta.env.MODE !== 'test';

async function loadAuthProviders({ force = false } = {}) {
    if (AUTH_PROVIDER_CACHE_ENABLED && !force && cachedAuthProviders) {
        return cachedAuthProviders;
    }

    if (AUTH_PROVIDER_CACHE_ENABLED && !force && pendingAuthProvidersRequest) {
        return pendingAuthProvidersRequest;
    }

    pendingAuthProvidersRequest = api
        .get('/auth/providers')
        .then((response) => {
            const providers = Array.isArray(response.data.data) ? response.data.data : [];

            if (AUTH_PROVIDER_CACHE_ENABLED) {
                cachedAuthProviders = providers;
            }

            return providers;
        })
        .finally(() => {
            pendingAuthProvidersRequest = null;
        });

    return pendingAuthProvidersRequest;
}

/**
 * Check whether the current screen needs auth-provider metadata before idle time.
 *
 * @param {string} pathname
 * @returns {boolean}
 */
function shouldLoadAuthProvidersImmediately(pathname) {
    return AUTH_PROVIDER_IMMEDIATE_PATHS.some((path) => pathname.startsWith(path));
}

/**
 * Check whether the current screen can start without user-session hydration.
 *
 * @param {string} pathname
 * @returns {boolean}
 */
function isPublicAuthPath(pathname) {
    return PUBLIC_AUTH_PATHS.some((path) => pathname.startsWith(path));
}

/**
 * Check whether the current public auth screen needs provider metadata.
 *
 * @param {string} pathname
 * @returns {boolean}
 */
function shouldSkipAuthProviders(pathname) {
    return isPublicAuthPath(pathname) && !shouldLoadAuthProvidersImmediately(pathname);
}

/**
 * Return providers that expose one capability.
 *
 * @param {AuthRuntimeProvider[]} providers
 * @param {string} capability
 * @returns {AuthRuntimeProvider[]}
 */
function filterProvidersByCapability(providers, capability) {
    return providers.filter((provider) => provider?.capabilities?.[capability]);
}

/**
 * Return providers that can still be linked to the current account.
 *
 * @param {AuthRuntimeProvider[]} providers
 * @param {AuthUser | null} user
 * @returns {AuthRuntimeProvider[]}
 */
function resolveLinkableProviders(providers, user) {
    const linkedProviderKeys = new Set(
        (user?.linked_providers ?? [])
            .map((provider) => provider?.key)
            .filter((key) => typeof key === 'string' && key.trim() !== ''),
    );

    return filterProvidersByCapability(providers, 'link_account').filter(
        (provider) =>
            (provider?.capabilities?.requires_redirect === true || provider?.type === 'oauth2') &&
            !linkedProviderKeys.has(provider.key),
    );
}

/**
 * Schedule non-critical startup work without blocking first paint.
 *
 * @param {() => void} callback
 * @returns {() => void}
 */
function scheduleIdleStartup(callback) {
    if (typeof window === 'undefined') {
        callback();

        return () => {};
    }

    if ('requestIdleCallback' in window) {
        const idleHandle = window.requestIdleCallback(callback, { timeout: 1200 });

        return () => window.cancelIdleCallback(idleHandle);
    }

    const timeoutHandle = window.setTimeout(callback, 1);

    return () => window.clearTimeout(timeoutHandle);
}

/**
 * Provide the authenticated user and runtime auth-provider contracts to the SPA.
 *
 * @param {{ children: import('react').ReactNode }} props
 * @returns {import('react').JSX.Element}
 */
export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [authProviders, setAuthProviders] = useState([]);
    const [providersLoading, setProvidersLoading] = useState(true);
    const [providersError, setProvidersError] = useState(null);

    const refreshUser = useCallback(async () => {
        if (isPublicAuthPath(window.location.pathname)) {
            setUser(null);
            setLoading(false);

            return;
        }

        try {
            const response = await api.get('/auth/me');
            setUser(response.data.data ?? null);
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    }, []);

    const refreshAuthProviders = useCallback(async ({ force = false } = {}) => {
        if (AUTH_PROVIDER_CACHE_ENABLED && !force && cachedAuthProviders) {
            setAuthProviders(cachedAuthProviders);
            setProvidersError(null);
            setProvidersLoading(false);

            return;
        }

        setProvidersLoading(true);
        setProvidersError(null);

        try {
            const providers = await loadAuthProviders({ force });
            setAuthProviders(providers);
        } catch {
            setAuthProviders([]);
            setProvidersError('load_failed');
        } finally {
            setProvidersLoading(false);
        }
    }, []);

    useEffect(() => {
        void refreshUser();

        const pathname = window.location.pathname;

        if (shouldSkipAuthProviders(pathname)) {
            setProvidersLoading(false);
            setProvidersError(null);

            return undefined;
        }

        if (shouldLoadAuthProvidersImmediately(pathname)) {
            void refreshAuthProviders();

            return undefined;
        }

        return scheduleIdleStartup(() => {
            void refreshAuthProviders();
        });
    }, [refreshAuthProviders, refreshUser]);

    const login = useCallback(async (payload) => {
        const response = await api.post('/auth/login', payload);
        setUser(response.data.data ?? null);
        return response.data;
    }, []);

    const register = useCallback(async (payload) => {
        const response = await api.post('/auth/register', payload);
        setUser(null);
        return response.data;
    }, []);

    const logout = useCallback(async () => {
        try {
            await api.post('/auth/logout');
        } finally {
            setUser(null);
            await refreshAuthProviders({ force: true });
        }
    }, [refreshAuthProviders]);
    const hasEmailLogin = useMemo(
        () => authProviders.some((provider) => provider.key === 'email'),
        [authProviders],
    );
    const loginProviders = useMemo(
        () => filterProvidersByCapability(authProviders, 'login'),
        [authProviders],
    );
    const registerProviders = useMemo(
        () => filterProvidersByCapability(authProviders, 'register'),
        [authProviders],
    );
    const linkableProviders = useMemo(
        () => resolveLinkableProviders(authProviders, user),
        [authProviders, user],
    );

    const value = useMemo(
        () => ({
            user,
            loading,
            authProviders,
            providersLoading,
            providersError,
            isAuthenticated: Boolean(user),
            hasEmailLogin,
            loginProviders,
            registerProviders,
            linkableProviders,
            login,
            register,
            logout,
            refreshUser,
            refreshAuthProviders,
        }),
        [
            authProviders,
            hasEmailLogin,
            linkableProviders,
            loading,
            loginProviders,
            providersLoading,
            providersError,
            registerProviders,
            user,
            login,
            register,
            logout,
            refreshUser,
            refreshAuthProviders,
        ],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

/**
 * Read the shared auth context and fail fast when it is missing.
 *
 * @returns {AuthContextValue}
 */
export function useAuth() {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error('useAuth must be used within AuthProvider');
    }

    return context;
}
