import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LoginPage from './LoginPage';

const useAuthMock = vi.fn();
const useLanguageMock = vi.fn();

vi.mock('../context/AuthContext', () => ({
    useAuth: () => useAuthMock(),
}));

vi.mock('../../i18n/context/LanguageContext', () => ({
    useLanguage: () => useLanguageMock(),
}));

vi.mock('../components/AuthShowcase', () => ({
    default: () => <div>Auth showcase</div>,
}));

vi.mock('../../../components/layout/LanguageSelect', () => ({
    default: () => <div>Language select</div>,
}));

vi.mock('../components/SensitiveInput', () => ({
    default: ({ value = '', onChange, autoComplete, required }) => (
        <input
            type="password"
            value={value}
            onChange={onChange}
            autoComplete={autoComplete}
            required={required}
        />
    ),
}));

function renderPage() {
    return render(
        <MemoryRouter initialEntries={['/login']}>
            <LoginPage />
        </MemoryRouter>,
    );
}

function buildProvider(overrides = {}) {
    return {
        key: 'email',
        display_name: 'Email and Password',
        type: 'password',
        icon: 'mail',
        capabilities: {
            login: true,
            register: true,
            requires_redirect: false,
            supports_password: true,
        },
        metadata: {},
        ...overrides,
    };
}

beforeEach(() => {
    useLanguageMock.mockReturnValue({
        t: (key) =>
            ({
                'auth.account': 'OPAS account',
                'auth.backHome': 'Back to home',
                'auth.loginTitle': 'Login',
                'auth.loginText': 'Sign in to continue your work inside OPAS.',
                'auth.providersLoading': 'Loading sign-in methods...',
                'auth.providersLoadError': 'Unable to load authentication methods right now.',
                'auth.noProvidersAvailable': 'No authentication providers are currently available.',
                'auth.invalidEmail': 'Enter a valid email address.',
                'auth.email': 'Email',
                'auth.password': 'Password',
                'auth.showValue': 'Show value',
                'auth.hideValue': 'Hide value',
                'auth.loginSubmitting': 'Signing in...',
                'auth.loginSubmit': 'Sign in',
                'auth.forgotPasswordLink': 'Forgot password?',
                'auth.noAccount': "Don't have an account?",
                'auth.createAccount': 'Create a new account',
                'auth.registrationUnavailable': 'Account registration is currently unavailable.',
                'auth.continueWithProvider': 'Continue with',
                'auth.registerWithProvider': 'Register with',
                'auth.providerUiUnavailableSuffix':
                    'is available but this screen does not support it yet.',
                'auth.loginError': 'Unable to sign in with this account.',
                'auth.resetPasswordSuccess': 'Password updated successfully. You can sign in now.',
                'auth.loginEyebrow': 'Welcome Back',
                'auth.loginShowcaseTitle': 'Login showcase',
                'auth.loginShowcaseText': 'Login showcase text',
                'auth.marketTracking': 'Market tracking',
                'auth.contentWorkflow': 'Content workflow',
                'auth.automationAccess': 'Automation access',
            })[key] ?? key,
    });

    useAuthMock.mockReturnValue({
        login: vi.fn(),
        loginProviders: [],
        registerProviders: [],
        providersLoading: false,
        providersError: null,
    });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('LoginPage', () => {
    it('renders only enabled login providers and keeps backend order', () => {
        useAuthMock.mockReturnValue({
            login: vi.fn(),
            providersLoading: false,
            providersError: null,
            registerProviders: [buildProvider()],
            loginProviders: [
                buildProvider(),
                buildProvider({
                    key: 'github',
                    display_name: 'GitHub',
                    type: 'oauth2',
                    icon: 'github',
                    capabilities: {
                        login: true,
                        register: true,
                        requires_redirect: true,
                        supports_password: false,
                    },
                    metadata: {
                        redirect_url: '/api/auth/providers/github/redirect',
                    },
                }),
                buildProvider({
                    key: 'google',
                    display_name: 'Google',
                    type: 'oauth2',
                    icon: 'google',
                    capabilities: {
                        login: true,
                        register: true,
                        requires_redirect: true,
                        supports_password: false,
                    },
                    metadata: {
                        redirect_url: '/api/auth/providers/google/redirect',
                    },
                }),
            ],
        });

        renderPage();

        expect(screen.getAllByRole('textbox')).toHaveLength(1);
        const githubLink = screen.getByRole('link', { name: 'Continue with GitHub' });
        const googleLink = screen.getByRole('link', { name: 'Continue with Google' });

        expect(githubLink).toHaveAttribute('href', '/api/auth/providers/github/redirect');
        expect(googleLink).toHaveAttribute('href', '/api/auth/providers/google/redirect');
        expect(
            githubLink.compareDocumentPosition(googleLink) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).not.toBe(0);
        expect(
            screen.queryByRole('link', { name: 'Continue with Facebook' }),
        ).not.toBeInTheDocument();
    });

    it('shows the empty state when no login providers are available', () => {
        renderPage();

        expect(
            screen.getByText('No authentication providers are currently available.'),
        ).toBeInTheDocument();
        expect(screen.queryAllByRole('textbox')).toHaveLength(0);
    });

    it('shows the provider load error without inventing an email fallback', () => {
        useAuthMock.mockReturnValue({
            login: vi.fn(),
            loginProviders: [],
            registerProviders: [],
            providersLoading: false,
            providersError: 'load_failed',
        });

        renderPage();

        expect(
            screen.getByText('Unable to load authentication methods right now.'),
        ).toBeInTheDocument();
        expect(screen.queryAllByRole('textbox')).toHaveLength(0);
    });
});
