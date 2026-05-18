import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api from '../../../lib/api';

const AuthContext = createContext(null);
const fallbackEmailProvider = {
    key: 'email',
    display_name: 'Email and Password',
    type: 'password',
    icon: 'mail',
    visibility: 'public',
    active: true,
    capabilities: {
        login: true,
        register: true,
        requires_redirect: false,
        supports_password: true,
    },
    metadata: {
        button_text: 'Continue with email',
    },
};

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [authProviders, setAuthProviders] = useState([]);
    const [providersLoading, setProvidersLoading] = useState(true);

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
        try {
            const response = await api.get('/auth/providers');
            const providers = response.data.data ?? [];
            setAuthProviders(providers.length > 0 ? providers : [fallbackEmailProvider]);
        } catch {
            setAuthProviders([fallbackEmailProvider]);
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
        setUser(response.data.data ?? null);
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
            isAuthenticated: Boolean(user),
            hasEmailLogin: authProviders.some((provider) => provider.key === 'email'),
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
