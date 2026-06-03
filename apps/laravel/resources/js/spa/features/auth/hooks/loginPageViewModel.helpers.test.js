import { describe, expect, it } from 'vitest';
import {
    buildLoginProvidersViewModel,
    buildLoginRegistrationViewModel,
} from './loginPageViewModel.helpers';

function buildProvider(overrides = {}) {
    return {
        key: 'email',
        display_name: 'Email and Password',
        type: 'password',
        capabilities: {
            supports_password: true,
        },
        metadata: {},
        ...overrides,
    };
}

describe('loginPageViewModel.helpers', () => {
    it('separates password provider from secondary login actions', () => {
        const emailProvider = buildProvider();
        const githubProvider = buildProvider({
            key: 'github',
            display_name: 'GitHub',
            type: 'oauth2',
        });

        expect(
            buildLoginProvidersViewModel({
                loginProviders: [emailProvider, githubProvider],
                emailProvider,
                providersLoading: false,
                providersError: null,
            }),
        ).toEqual({
            items: [githubProvider],
            loading: false,
            error: null,
            hasLoginProviders: true,
        });
    });

    it('keeps registration providers as a dedicated availability contract', () => {
        const providers = [buildProvider()];

        expect(buildLoginRegistrationViewModel(providers)).toEqual({ providers });
    });
});
