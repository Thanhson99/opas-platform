import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import api from '../../../lib/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const refreshUser = async () => {
        try {
            const response = await api.get('/auth/me');
            setUser(response.data.data ?? null);
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void refreshUser();
    }, []);

    const login = async (payload) => {
        const response = await api.post('/auth/login', payload);
        setUser(response.data.data ?? null);
        return response.data;
    };

    const register = async (payload) => {
        const response = await api.post('/auth/register', payload);
        setUser(response.data.data ?? null);
        return response.data;
    };

    const logout = async () => {
        await api.post('/auth/logout');
        setUser(null);
    };

    const value = useMemo(
        () => ({
            user,
            loading,
            isAuthenticated: Boolean(user),
            login,
            register,
            logout,
            refreshUser,
        }),
        [loading, user],
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
