import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Header from './Header';

const useAuthMock = vi.fn();
const useLanguageMock = vi.fn();

vi.mock('../../features/auth/context/AuthContext', () => ({
    useAuth: () => useAuthMock(),
}));

vi.mock('../../features/i18n/context/LanguageContext', () => ({
    useLanguage: () => useLanguageMock(),
}));

vi.mock('./LanguageSelect', () => ({
    default: () => <div>Language select</div>,
}));

function renderHeader() {
    return render(
        <MemoryRouter>
            <Header title="Workspace" description="Shared workspace" onToggleSidebar={() => {}} />
        </MemoryRouter>,
    );
}

describe('Header', () => {
    beforeEach(() => {
        useLanguageMock.mockReturnValue({
            t: (key) =>
                ({
                    'common.loadingAccount': 'Loading account...',
                    'common.logout': 'Logout',
                    'common.login': 'Login',
                    'common.register': 'Register',
                    'header.alertsButton': 'Open alerts',
                    'header.searchPlaceholder': 'Search modules...',
                    'header.toggleNavigation': 'Toggle navigation',
                    'header.guest': 'Guest',
                    'header.guestHint': 'Sign in to unlock more features.',
                    'header.accountMenu': 'Open account menu',
                    'header.accountOverview': 'Account details',
                    'header.workspaceEyebrow': 'OPAS Workspace',
                    'header.searchLabel': 'Search workspace modules',
                })[key] ?? key,
        });
    });

    afterEach(() => {
        cleanup();
        vi.clearAllMocks();
    });

    it('shows the authenticated account summary, submenu links, and logout action', () => {
        useAuthMock.mockReturnValue({
            user: {
                name: 'OPAS User',
                email: 'user@example.com',
                role_label: 'Member',
            },
            loading: false,
            logout: vi.fn(),
            registerProviders: [],
        });

        renderHeader();

        expect(screen.getByText(/OPAS User/)).toBeInTheDocument();
        expect(screen.getByText('Member')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Open account menu' }));

        expect(screen.getByRole('menuitem', { name: 'Account details' })).toHaveAttribute(
            'href',
            '/account',
        );
        expect(screen.getByRole('menuitem', { name: 'Logout' })).toBeInTheDocument();
    });

    it('closes the authenticated account menu after outside interaction', () => {
        useAuthMock.mockReturnValue({
            user: {
                name: 'OPAS User',
                email: 'user@example.com',
                role_label: 'Member',
            },
            loading: false,
            logout: vi.fn(),
            registerProviders: [],
        });

        renderHeader();

        fireEvent.click(screen.getByRole('button', { name: 'Open account menu' }));
        expect(screen.getByRole('menuitem', { name: 'Account details' })).toBeInTheDocument();

        fireEvent.mouseDown(document.body);

        expect(screen.queryByRole('menuitem', { name: 'Account details' })).not.toBeInTheDocument();
    });

    it('shows guest account actions and shared utility controls', () => {
        useAuthMock.mockReturnValue({
            user: null,
            loading: false,
            logout: vi.fn(),
            registerProviders: [{ key: 'email' }],
        });

        renderHeader();

        expect(screen.getByRole('heading', { name: 'Workspace' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Toggle navigation' })).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Search modules...')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Open alerts' })).toBeInTheDocument();
        expect(screen.getByText('Language select')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Login' })).toHaveAttribute('href', '/login');
        expect(screen.getByRole('link', { name: 'Register' })).toHaveAttribute('href', '/register');
    });
});
