import { describe, expect, it, vi } from 'vitest';
import { getProviderActionText } from './publicAuthProviders';

describe('getProviderActionText', () => {
    /**
     * Protect provider-aware fallback labels when admins leave custom button text blank.
     */
    it('falls back to a provider-specific login label when button text is blank', () => {
        const label = getProviderActionText(
            {
                display_name: 'GitHub',
                metadata: {
                    button_text: '   ',
                },
            },
            'login',
            vi.fn(
                (key) =>
                    ({
                        'auth.continueWithProvider': 'Continue with',
                        'auth.registerWithProvider': 'Register with',
                    })[key] ?? key,
            ),
        );

        expect(label).toBe('Continue with GitHub');
    });
});
