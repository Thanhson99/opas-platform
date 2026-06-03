import { memo, useCallback } from 'react';
import TelegramBotChipSelect from './TelegramBotChipSelect';
import TelegramBotTagInput from './TelegramBotTagInput';
import { TELEGRAM_ACTION_OPTIONS, TELEGRAM_UPDATE_OPTIONS } from './telegramBotAdmin.helpers';

/**
 * Render the access-control tab for chat, user, action, and update allow-lists.
 *
 * @param {{
 *   t: (key: string) => string,
 *   form: Record<string, any>,
 *   onChange: (field: string, value: any) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotAccessControlTab({ t, form, onChange }) {
    const handleAllowedChatIdsChange = useCallback(
        (values) => onChange('allowed_chat_ids', values),
        [onChange],
    );
    const handleAllowedUserIdsChange = useCallback(
        (values) => onChange('allowed_user_ids', values),
        [onChange],
    );
    const handleAllowedActionsChange = useCallback(
        (values) => onChange('allowed_actions', values),
        [onChange],
    );
    const handleAllowedUpdatesChange = useCallback(
        (values) => onChange('allowed_updates', values),
        [onChange],
    );

    return (
        <div className="admin-telegram-bots__stack">
            <section className="admin-telegram-bots__card">
                <header className="admin-telegram-bots__card-head">
                    <div>
                        <h4>{t('adminTelegramBots.sections.accessControl')}</h4>
                        <p>{t('adminTelegramBots.sections.accessControlText')}</p>
                    </div>
                </header>
                <div className="admin-telegram-bots__detail-grid">
                    <TelegramBotTagInput
                        label={t('adminTelegramBots.fields.allowedChatIds')}
                        values={form.allowed_chat_ids}
                        placeholder={t('adminTelegramBots.placeholders.chatId')}
                        addLabel={t('adminTelegramBots.addTagButton')}
                        help={t('adminTelegramBots.fieldHelp.allowedChatIds')}
                        onChange={handleAllowedChatIdsChange}
                    />
                    <TelegramBotTagInput
                        label={t('adminTelegramBots.fields.allowedUserIds')}
                        values={form.allowed_user_ids}
                        placeholder={t('adminTelegramBots.placeholders.userId')}
                        addLabel={t('adminTelegramBots.addTagButton')}
                        help={t('adminTelegramBots.fieldHelp.allowedUserIds')}
                        onChange={handleAllowedUserIdsChange}
                    />
                </div>
            </section>

            <section className="admin-telegram-bots__card">
                <header className="admin-telegram-bots__card-head">
                    <div>
                        <h4>{t('adminTelegramBots.fields.allowedActions')}</h4>
                        <p>{t('adminTelegramBots.sections.allowedActionsText')}</p>
                    </div>
                </header>
                <TelegramBotChipSelect
                    label={t('adminTelegramBots.fields.allowedActions')}
                    values={form.allowed_actions}
                    options={TELEGRAM_ACTION_OPTIONS}
                    optionPrefix="adminTelegramBots.actionLabels"
                    help={t('adminTelegramBots.fieldHelp.allowedActions')}
                    t={t}
                    onChange={handleAllowedActionsChange}
                />
            </section>

            <section className="admin-telegram-bots__card">
                <header className="admin-telegram-bots__card-head">
                    <div>
                        <h4>{t('adminTelegramBots.fields.allowedUpdates')}</h4>
                        <p>{t('adminTelegramBots.sections.allowedUpdatesText')}</p>
                    </div>
                </header>
                <TelegramBotChipSelect
                    label={t('adminTelegramBots.fields.allowedUpdates')}
                    values={form.allowed_updates}
                    options={TELEGRAM_UPDATE_OPTIONS}
                    optionPrefix="adminTelegramBots.updateLabels"
                    help={t('adminTelegramBots.fieldHelp.allowedUpdates')}
                    t={t}
                    onChange={handleAllowedUpdatesChange}
                />
            </section>
        </div>
    );
}

export default memo(TelegramBotAccessControlTab);
