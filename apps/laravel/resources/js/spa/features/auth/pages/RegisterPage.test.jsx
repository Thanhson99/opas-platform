import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RegisterPage from './RegisterPage';

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
        <MemoryRouter initialEntries={['/register']}>
            <RegisterPage />
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
                'auth.registerTitle': 'Register',
                'auth.registerText': 'Create a new account and start with the member role.',
                'auth.providersLoading': 'Loading sign-in methods...',
                'auth.providersLoadError': 'Unable to load authentication methods right now.',
                'auth.registrationUnavailable': 'Account registration is currently unavailable.',
                'auth.name': 'Name',
                'auth.email': 'Email',
                'auth.invalidEmail': 'Enter a valid email address.',
                'auth.password': 'Password',
                'auth.confirmPassword': 'Confirm password',
                'auth.showValue': 'Show value',
                'auth.hideValue': 'Hide value',
                'auth.passwordRuleIntro': 'Your password must satisfy all of these rules:',
                'auth.passwordRuleFail': 'Missing:',
                'auth.passwordConfirmMismatch': 'The password confirmation does not match.',
                'auth.registerSubmitting': 'Creating...',
                'auth.registerSubmit': 'Create account',
                'auth.haveAccount': 'Already have an account?',
                'auth.goToLogin': 'Go to login',
                'auth.registerWithProvider': 'Register with',
                'auth.providerUiUnavailableSuffix':
                    'is available but this screen does not support it yet.',
                'auth.registerError': 'Unable to create this account. Please review your details.',
                'auth.registerEyebrow': 'Create Your Access',
                'auth.registerShowcaseTitle': 'Register showcase',
                'auth.registerShowcaseText': 'Register showcase text',
                'auth.member': 'Member',
                'auth.plus': 'Plus',
                'auth.vip': 'VIP',
                'auth.admin': 'Admin',
                'auth.passwordRuleMinLength': 'at least 8 characters',
                'auth.passwordRuleLowercase': 'at least one lowercase letter',
                'auth.passwordRuleUppercase': 'at least one uppercase letter',
                'auth.passwordRuleNumber': 'at least one number',
                'auth.passwordRuleSymbol': 'at least one special character',
            })[key] ?? key,
    });

    useAuthMock.mockReturnValue({
        register: vi.fn(),
        registerProviders: [],
        providersLoading: false,
        providersError: null,
    });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('RegisterPage', () => {
    it('renders password and redirect registration methods dynamically', () => {
        useAuthMock.mockReturnValue({
            register: vi.fn(),
            providersLoading: false,
            providersError: null,
            registerProviders: [
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
            ],
        });

        renderPage();

        expect(screen.getAllByRole('textbox')).toHaveLength(2);
        expect(screen.getByRole('link', { name: 'Register with GitHub' })).toHaveAttribute(
            'href',
            '/api/auth/providers/github/redirect',
        );
    });

    it('shows the register unavailable state when no register providers exist', () => {
        renderPage();

        expect(
            screen.getByText('Account registration is currently unavailable.'),
        ).toBeInTheDocument();
        expect(screen.queryAllByRole('textbox')).toHaveLength(0);
    });

    it('shows the provider load error when provider discovery fails', () => {
        useAuthMock.mockReturnValue({
            register: vi.fn(),
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
