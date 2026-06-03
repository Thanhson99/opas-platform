import AuthProviderOptions from './AuthProviderOptions';

/**
 * Render provider actions with the cyber divider used by auth panels.
 *
 * @param {{
 *   action: 'login' | 'register',
 *   label: string,
 *   providers: Array<import('../lib/publicAuthProviders').PublicAuthProvider>,
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element | null}
 */
export default function AuthProviderGroup({ action, label, providers, t }) {
    if (providers.length === 0) {
        return null;
    }

    return (
        <div className="app-auth-provider-group">
            <span>{label}</span>
            <AuthProviderOptions providers={providers} action={action} t={t} />
        </div>
    );
}
