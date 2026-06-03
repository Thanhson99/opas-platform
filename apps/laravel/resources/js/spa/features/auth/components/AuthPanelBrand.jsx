/**
 * Render the shared auth brand block.
 *
 * @param {{ t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthPanelBrand({ t }) {
    return (
        <span className="app-auth-panel__brand">
            <img src="/storage/images/brand/opas-logo-mark.png" alt="" aria-hidden="true" />
            <span className="app-auth-panel__brand-copy">
                <strong>{t('auth.account')}</strong>
                <small>{t('auth.secureAccess')}</small>
            </span>
        </span>
    );
}
