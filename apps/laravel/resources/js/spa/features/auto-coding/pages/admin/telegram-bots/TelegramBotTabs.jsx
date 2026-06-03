import { memo, useCallback } from 'react';
import AppIcon from '../../../../../components/icons/AppIcon';
import { TELEGRAM_BOT_TABS } from './telegramBotAdmin.helpers';

const tabIcons = {
    overview: 'info',
    access: 'shield',
    runtime: 'play',
    secrets: 'lock',
    audit: 'activity',
};

/**
 * Render the detail panel tab controls.
 *
 * @param {{
 *   t: (key: string) => string,
 *   activeTab: string,
 *   onTabChange: (tab: string) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotTabs({ t, activeTab, onTabChange }) {
    const handleTabClick = useCallback(
        (event) => {
            onTabChange(event.currentTarget.dataset.tab);
        },
        [onTabChange],
    );

    return (
        <div className="admin-telegram-bots__tabs-wrap">
            <nav className="admin-telegram-bots__tabs" role="tablist">
                {TELEGRAM_BOT_TABS.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        role="tab"
                        data-tab={tab}
                        aria-selected={activeTab === tab}
                        aria-controls={`telegram-bot-tab-panel-${tab}`}
                        id={`telegram-bot-tab-${tab}`}
                        title={t(`adminTelegramBots.tabHelp.${tab}`)}
                        className={`admin-telegram-bots__tab ${activeTab === tab ? 'is-active' : ''}`}
                        onClick={handleTabClick}
                    >
                        <AppIcon name={tabIcons[tab]} />
                        {t(`adminTelegramBots.tabs.${tab}`)}
                    </button>
                ))}
            </nav>
            <p className="admin-telegram-bots__tab-help">
                <AppIcon name="info" />
                <span>{t(`adminTelegramBots.tabHelp.${activeTab}`)}</span>
            </p>
        </div>
    );
}

export default memo(TelegramBotTabs);
