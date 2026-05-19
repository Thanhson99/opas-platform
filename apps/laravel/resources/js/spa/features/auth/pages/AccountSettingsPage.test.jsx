import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AccountSettingsPage from './AccountSettingsPage';

const useAuthMock = vi.fn();
const useLanguageMock = vi.fn();

vi.mock('../context/AuthContext', () => ({
    useAuth: () => useAuthMock(),
}));

vi.mock('../../i18n/context/LanguageContext', () => ({
    useLanguage: () => useLanguageMock(),
}));

vi.mock('../../../lib/api', () => ({
    default: {
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('../../../components/ui/ConfirmModal', () => ({
    default: () => null,
}));

function renderPage() {
    return render(
        <MemoryRouter>
            <AccountSettingsPage />
        </MemoryRouter>,
    );
}

describe('AccountSettingsPage', () => {
    beforeEach(() => {
        useLanguageMock.mockReturnValue({
            t: (key) =>
                ({
                    'common.loadingAccount': 'Loading account...',
                    'common.cancel': 'Cancel',
                    'auth.name': 'Name',
                    'auth.email': 'Email',
                    'accountSettings.eyebrow': 'Account Links',
                    'accountSettings.title':
                        'Keep your profile simple and your sign-in methods safe.',
                    'accountSettings.text': 'Manage your account settings.',
                    'accountSettings.currentProvider': 'Current sign-in method',
                    'accountSettings.sessionLabel': 'Current session',
                    'accountSettings.emailFallbackEnabled': 'Email recovery available',
                    'accountSettings.profileTitle': 'Profile details',
                    'accountSettings.profileText': 'Only the display name is editable here.',
                    'accountSettings.emailLocked': 'Email is locked.',
                    'accountSettings.save': 'Save profile',
                    'accountSettings.linkedProvidersTitle': 'Linked providers',
                    'accountSettings.linkedProvidersText':
                        'These providers can sign into the same account.',
                    'accountSettings.currentProviderLocked':
                        'Current session is using this provider, so unlink is blocked.',
                    'accountSettings.linkedReady': 'Linked and ready to use.',
                    'accountSettings.unlink': 'Unlink',
                    'accountSettings.availableProvidersTitle': 'Link another sign-in method',
                    'accountSettings.availableProvidersText':
                        'Only providers enabled and ready in admin settings appear here.',
                    'accountSettings.connectWith': 'Connect with',
                    'accountSettings.passwordHelp':
                        'Use password reset to start using email and password later.',
                    'accountSettings.resetPassword': 'Set password',
                })[key] ?? key,
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('shows linked providers, the current session provider lock, and hides extra link panel when empty', () => {
        useAuthMock.mockReturnValue({
            user: {
                name: 'OPAS User',
                email: 'user@example.com',
                current_sign_in_provider: {
                    key: 'google',
                    display_name: 'Google',
                    icon: 'google',
                },
                linked_providers: [
                    {
                        key: 'google',
                        display_name: 'Google',
                        icon: 'google',
                    },
                    {
                        key: 'github',
                        display_name: 'GitHub',
                        icon: 'github',
                    },
                ],
            },
            loading: false,
            isAuthenticated: true,
            hasEmailLogin: true,
            linkableProviders: [],
            refreshUser: vi.fn(),
            refreshAuthProviders: vi.fn(),
        });

        renderPage();

        expect(screen.getByText('Profile details')).toBeInTheDocument();
        expect(screen.getByText('Linked providers')).toBeInTheDocument();
        expect(screen.getAllByText('Google').length).toBeGreaterThan(0);
        expect(
            screen.getByText('Current session is using this provider, so unlink is blocked.'),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Unlink' })[0]).toBeDisabled();
        expect(screen.queryByText('Link another sign-in method')).not.toBeInTheDocument();
    });
});
