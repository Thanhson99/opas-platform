import { renderPublicAuthProvider } from '../lib/publicAuthProviderRenderers';

/**
 * Render the public auth-provider actions available for one page context.
 */
export default function AuthProviderOptions({ providers, action, t }) {
    if (providers.length === 0) {
        return null;
    }

    return (
        <div className="app-form">
            {providers.map((provider) => renderPublicAuthProvider(provider, action, t))}
        </div>
    );
}
