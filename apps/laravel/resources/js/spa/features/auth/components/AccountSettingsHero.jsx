import PageHero from '../../../components/ui/PageHero';

/**
 * Render the account settings page hero.
 *
 * @param {{
 *   currentSignInProvider?: Record<string, unknown>,
 *   hasEmailLogin: boolean,
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function AccountSettingsHero({ currentSignInProvider, hasEmailLogin, t }) {
    const providerName =
        currentSignInProvider?.display_name ?? t('accountSettings.unknownProvider');

    return (
        <PageHero
            eyebrow={t('accountSettings.eyebrow')}
            title={t('accountSettings.title')}
            text={t('accountSettings.text')}
            aside={
                <div className="app-role-card">
                    <strong>{t('accountSettings.currentProvider')}</strong>
                    <span>{providerName}</span>
                </div>
            }
        >
            <span className="app-status-pill app-status-pill--muted">
                {t('accountSettings.sessionLabel')}: {providerName}
            </span>
            {hasEmailLogin ? (
                <span className="app-status-pill app-status-pill--success">
                    {t('accountSettings.emailFallbackEnabled')}
                </span>
            ) : null}
        </PageHero>
    );
}
