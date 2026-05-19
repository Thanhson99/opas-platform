import { cleanup, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import api from '../../../lib/api';
import { AuthProvider, useAuth } from './AuthContext';

vi.mock('../../../lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

function AuthSnapshot() {
    const { loginProviders, registerProviders, authProviders, providersError, providersLoading } =
        useAuth();

    return (
        <div>
            <span data-testid="loading">{providersLoading ? 'yes' : 'no'}</span>
            <span data-testid="providers">{authProviders.length}</span>
            <span data-testid="login-providers">{loginProviders.length}</span>
            <span data-testid="register-providers">{registerProviders.length}</span>
            <span data-testid="providers-error">{providersError ?? 'none'}</span>
        </div>
    );
}

describe('AuthContext', () => {
    afterEach(() => {
        cleanup();
        vi.clearAllMocks();
    });

    it('keeps an empty provider list empty when the backend exposes no providers', async () => {
        api.get.mockImplementation((url) => {
            if (url === '/auth/providers') {
                return Promise.resolve({ data: { data: [] } });
            }

            if (url === '/auth/me') {
                return Promise.reject(new Error('Unauthenticated.'));
            }

            throw new Error(`Unexpected url: ${url}`);
        });

        render(
            <AuthProvider>
                <AuthSnapshot />
            </AuthProvider>,
        );

        await waitFor(() => {
            expect(screen.getByTestId('loading')).toHaveTextContent('no');
        });

        expect(screen.getByTestId('providers')).toHaveTextContent('0');
        expect(screen.getByTestId('login-providers')).toHaveTextContent('0');
        expect(screen.getByTestId('register-providers')).toHaveTextContent('0');
        expect(screen.getByTestId('providers-error')).toHaveTextContent('none');
    });

    it('reports provider loading failures instead of inventing a fallback provider', async () => {
        api.get.mockImplementation((url) => {
            if (url === '/auth/providers') {
                return Promise.reject(new Error('Provider lookup failed.'));
            }

            if (url === '/auth/me') {
                return Promise.reject(new Error('Unauthenticated.'));
            }

            throw new Error(`Unexpected url: ${url}`);
        });

        render(
            <AuthProvider>
                <AuthSnapshot />
            </AuthProvider>,
        );

        await waitFor(() => {
            expect(screen.getByTestId('loading')).toHaveTextContent('no');
        });

        expect(screen.getByTestId('providers')).toHaveTextContent('0');
        expect(screen.getByTestId('login-providers')).toHaveTextContent('0');
        expect(screen.getByTestId('register-providers')).toHaveTextContent('0');
        expect(screen.getByTestId('providers-error')).toHaveTextContent('load_failed');
    });
});
