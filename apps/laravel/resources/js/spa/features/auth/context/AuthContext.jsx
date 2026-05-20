import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api from '../../../lib/api';
import { getLinkableProviders, getProvidersForCapability } from '../lib/publicAuthProviders';

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
 *   refreshAuthProviders: () => Promise<void>,
 * }} AuthContextValue
 */

/** @type {import('react').Context<AuthContextValue | null>} */
const AuthContext = createContext(null);

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
        try {
            const response = await api.get('/auth/me');
            setUser(response.data.data ?? null);
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    }, []);

    const refreshAuthProviders = useCallback(async () => {
        setProvidersLoading(true);
        setProvidersError(null);

        try {
            const response = await api.get('/auth/providers');
            const providers = Array.isArray(response.data.data) ? response.data.data : [];
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
        void refreshAuthProviders();
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
            await refreshAuthProviders();
        }
    }, [refreshAuthProviders]);

    const value = useMemo(
        () => ({
            user,
            loading,
            authProviders,
            providersLoading,
            providersError,
            isAuthenticated: Boolean(user),
            hasEmailLogin: authProviders.some((provider) => provider.key === 'email'),
            loginProviders: getProvidersForCapability(authProviders, 'login'),
            registerProviders: getProvidersForCapability(authProviders, 'register'),
            linkableProviders: getLinkableProviders(authProviders, user),
            login,
            register,
            logout,
            refreshUser,
            refreshAuthProviders,
        }),
        [
            authProviders,
            loading,
            providersLoading,
            providersError,
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
