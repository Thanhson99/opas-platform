import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api from '../../../lib/api';
import { getLinkableProviders, getProvidersForCapability } from '../lib/publicAuthProviders';

const AuthContext = createContext(null);

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

export function useAuth() {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error('useAuth must be used within AuthProvider');
    }

    return context;
}
