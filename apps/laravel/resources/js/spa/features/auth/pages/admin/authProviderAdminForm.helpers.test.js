import { describe, expect, it, vi } from 'vitest';
import { buildFieldMeta } from './authProviderAdminForm.helpers';

describe('buildFieldMeta', () => {
    /**
     * Keep the admin placeholder aligned with the provider being edited.
     */
    it('builds a provider-specific button text placeholder', () => {
        const meta = buildFieldMeta(
            vi.fn(
                (key) =>
                    ({
                        'auth.continueWithProvider': 'Continue with',
                        'adminAuth.fields.button_text.label': 'Login button text',
                        'adminAuth.fields.button_text.description':
                            'Text shown on the login button.',
                        'adminAuth.fields.button_text.placeholder': 'Example: Continue with Google',
                    })[key] ?? key,
            ),
            'button_text',
            { providerDisplayName: 'Facebook' },
        );

        expect(meta.placeholder).toBe('Continue with Facebook');
    });
});
