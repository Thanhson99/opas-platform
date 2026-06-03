import { memo, useCallback } from 'react';
import SensitiveInput from '../../../../auth/components/SensitiveInput';
import AppIcon from '../../../../../components/icons/AppIcon';

/**
 * Render the secrets tab with stored-state, reveal, and replace controls.
 *
 * @param {{
 *   t: (key: string) => string,
 *   bot: Record<string, any>,
 *   form: Record<string, any>,
 *   revealedSecrets: Record<string, string>,
 *   onChange: (field: string, value: any) => void,
 *   onReveal: (secretKey: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotSecretRow({ t, bot, form, revealedSecrets, secretKey, onChange, onReveal }) {
    const handleReveal = useCallback(() => {
        onReveal(secretKey);
    }, [onReveal, secretKey]);

    const handleSecretChange = useCallback(
        (event) => onChange(secretKey, event.target.value),
        [onChange, secretKey],
    );
    const secretLabel =
        secretKey === 'bot_token'
            ? t('adminTelegramBots.fields.botToken')
            : t('adminTelegramBots.fields.webhookSecret');
    const revealLabel = t('adminTelegramBots.revealSecret');
    const unavailableRevealLabel = t('adminTelegramBots.revealSecretUnavailable');
    const hasStoredSecret = Boolean(bot.secret_status?.[secretKey]);
    const revealedValue = revealedSecrets[secretKey] || '';

    return (
        <div className="admin-telegram-bots__secret-row">
            <div className="admin-telegram-bots__secret-main">
                <span>{secretLabel}</span>
                <strong className={hasStoredSecret ? 'is-ready' : 'is-missing'}>
                    <AppIcon name={hasStoredSecret ? 'check' : 'info'} />
                    {hasStoredSecret
                        ? t('adminTelegramBots.secretStored')
                        : t('adminTelegramBots.secretMissing')}
                </strong>
                <p>
                    {hasStoredSecret
                        ? t('adminTelegramBots.secretStoredHelp')
                        : t('adminTelegramBots.secretMissingHelp')}
                </p>
            </div>

            <div className="admin-telegram-bots__secret-actions">
                <code className={revealedValue ? 'is-revealed' : ''}>
                    {revealedValue || t('adminTelegramBots.placeholders.hiddenSecret')}
                </code>
                <button
                    type="button"
                    className={!hasStoredSecret ? 'is-disabled-state' : ''}
                    disabled={!hasStoredSecret}
                    onClick={handleReveal}
                    aria-label={`${hasStoredSecret ? revealLabel : unavailableRevealLabel}: ${secretLabel}`}
                    title={`${hasStoredSecret ? revealLabel : unavailableRevealLabel}: ${secretLabel}`}
                >
                    <AppIcon name={hasStoredSecret ? 'eye' : 'lock'} />
                    {hasStoredSecret ? revealLabel : unavailableRevealLabel}
                </button>
            </div>

            <label className="admin-telegram-bots__field admin-telegram-bots__field--secret">
                <span>{t('adminTelegramBots.replaceSecret')}</span>
                <SensitiveInput
                    value={form[secretKey]}
                    onChange={handleSecretChange}
                    placeholder={t('adminTelegramBots.placeholders.secretKeep')}
                    revealLabel={t('auth.showValue')}
                    concealLabel={t('auth.hideValue')}
                />
                <small>{t('adminTelegramBots.secretReplaceHelp')}</small>
            </label>
        </div>
    );
}

const MemoizedTelegramBotSecretRow = memo(TelegramBotSecretRow);

function TelegramBotSecretsTab({ t, bot, form, revealedSecrets, onChange, onReveal }) {
    return (
        <section className="admin-telegram-bots__card">
            <header className="admin-telegram-bots__card-head">
                <div>
                    <h4>{t('adminTelegramBots.tabs.secrets')}</h4>
                    <p>{t('adminTelegramBots.sections.secretsText')}</p>
                </div>
            </header>
            <div className="admin-telegram-bots__secrets-list">
                {['bot_token', 'webhook_secret'].map((secretKey) => (
                    <MemoizedTelegramBotSecretRow
                        key={secretKey}
                        bot={bot}
                        form={form}
                        revealedSecrets={revealedSecrets}
                        secretKey={secretKey}
                        t={t}
                        onChange={onChange}
                        onReveal={onReveal}
                    />
                ))}
            </div>
        </section>
    );
}

export default memo(TelegramBotSecretsTab);
