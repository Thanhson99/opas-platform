import { fireEvent, render, screen } from '@testing-library/react';
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
                    'header.guest': 'Guest',
                    'header.guestHint': 'Sign in to unlock more features.',
                    'header.accountMenu': 'Open account menu',
                    'header.accountOverview': 'Account details',
                })[key] ?? key,
        });
    });

    afterEach(() => {
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

        expect(screen.getByText('OPAS User')).toBeInTheDocument();
        expect(screen.getByText('Member • user@example.com')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Logout' })).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Open account menu' }));

        expect(screen.getByRole('link', { name: 'Account details' })).toHaveAttribute(
            'href',
            '/account',
        );
    });
});
