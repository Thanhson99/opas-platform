export const TELEGRAM_ACTION_OPTIONS = [
    'help',
    'menu',
    'conversation',
    'clarify_intent',
    'clarify_issue_context',
    'confirm_pending',
    'chat_start',
    'chat_status',
    'chat_stop',
    'chat_reset',
    'create_task',
    'code',
    'review',
    'validate',
    'status',
    'validation_report',
    'github_status',
    'next_action',
    'follow_up',
    'queue',
    'changes',
    'summary',
    'worker_status',
    'webhook_status',
    'commands_sync',
    'cancel_task',
    'cancel_tasks',
    'delete_task',
    'delete_tasks',
    'purge_tasks',
    'reset',
    'resume',
];

export const TELEGRAM_UPDATE_OPTIONS = ['message', 'callback_query'];

export const TELEGRAM_CREATE_DEFAULT_ACTIONS = [
    'help',
    'menu',
    'chat_start',
    'chat_status',
    'chat_stop',
    'chat_reset',
    'status',
    'summary',
    'queue',
];

export const BOT_PURPOSE_OPTIONS = ['remote_control', 'support', 'alerts', 'operations'];

export const BOT_ENVIRONMENT_OPTIONS = ['local', 'staging', 'production', 'shared'];

export const BOT_LOCALE_OPTIONS = ['en', 'vi'];

export const TELEGRAM_BOT_TABS = ['overview', 'access', 'runtime', 'secrets', 'audit'];

export const BOT_LIST_PAGE_SIZE = 8;

/**
 * Resolve the visual badge tone for one bot environment.
 *
 * @param {string | null | undefined} value
 * @returns {string}
 */
export function resolveBotEnvironmentTone(value) {
    if (value === 'production') {
        return 'prod';
    }

    if (value === 'staging') {
        return 'staging';
    }

    if (value === 'local') {
        return 'dev';
    }

    return 'qa';
}

/**
 * Normalize one bot payload into the editable admin form contract.
 *
 * @param {Record<string, any>} bot
 * @returns {Record<string, any>}
 */
export function buildBotForm(bot) {
    return {
        display_name: bot.display_name ?? '',
        purpose: bot.purpose ?? 'remote_control',
        environment: bot.environment ?? 'local',
        machine_group: bot.machine_group ?? '',
        enabled: Boolean(bot.enabled),
        is_default: Boolean(bot.is_default),
        locale: bot.locale ?? 'en',
        api_base_url: bot.api_base_url ?? '',
        allowed_chat_ids: (bot.allowed_chat_ids ?? []).map((item) => String(item)),
        allowed_user_ids: (bot.allowed_user_ids ?? []).map((item) => String(item)),
        allowed_actions: bot.allowed_actions ?? [],
        allowed_updates: bot.public_config?.allowed_updates ?? [],
        bot_username: bot.public_config?.bot_username ?? '',
        description: bot.public_config?.description ?? '',
        chat_history_limit: String(bot.public_config?.chat_history_limit ?? 30),
        chat_session_timeline_limit: String(bot.public_config?.chat_session_timeline_limit ?? 6),
        bot_token: '',
        webhook_secret: '',
    };
}

/**
 * Normalize the create-bot drawer state.
 *
 * @param {string} [locale='en']
 * @returns {Record<string, any>}
 */
export function buildCreateForm(locale = 'en') {
    return {
        key: '',
        display_name: '',
        purpose: 'remote_control',
        environment: 'local',
        machine_group: '',
        locale: locale === 'vi' ? 'vi' : 'en',
        description: '',
        bot_token: '',
        webhook_secret: '',
        allowed_chat_ids: [],
        allowed_user_ids: [],
        allowed_actions: TELEGRAM_CREATE_DEFAULT_ACTIONS,
        allowed_updates: ['message', 'callback_query'],
        enabled: false,
        is_default: false,
    };
}

/**
 * Collapse backend validation payloads into one operator-facing message.
 *
 * @param {any} requestError
 * @param {string} fallbackMessage
 * @returns {string}
 */
export function firstErrorMessage(requestError, fallbackMessage) {
    const errors = requestError?.response?.data?.errors;

    if (errors && typeof errors === 'object') {
        const firstField = Object.values(errors)[0];

        if (Array.isArray(firstField) && firstField[0]) {
            return firstField[0];
        }
    }

    return requestError?.response?.data?.message || fallbackMessage;
}

/**
 * Compare one editable bot state against its original snapshot.
 *
 * @param {Record<string, any> | null} form
 * @param {Record<string, any> | null} initialForm
 * @returns {boolean}
 */
export function hasFormChanged(form, initialForm) {
    if (!form || !initialForm) {
        return false;
    }

    return JSON.stringify(form) !== JSON.stringify(initialForm);
}

/**
 * Build the payload expected by the Telegram bot admin API.
 *
 * @param {Record<string, any>} form
 * @returns {Record<string, any>}
 */
export function buildBotPayload(form) {
    const secretConfig = {};

    if (form.bot_token.trim()) {
        secretConfig.bot_token = form.bot_token.trim();
    }

    if (form.webhook_secret.trim()) {
        secretConfig.webhook_secret = form.webhook_secret.trim();
    }

    const payload = {
        display_name: form.display_name.trim(),
        purpose: form.purpose,
        environment: form.environment,
        machine_group: form.machine_group.trim() || null,
        enabled: Boolean(form.enabled),
        is_default: Boolean(form.is_default),
        locale: form.locale,
        api_base_url: form.api_base_url.trim() || null,
        allowed_chat_ids: uniqueStrings(form.allowed_chat_ids),
        allowed_user_ids: uniqueStrings(form.allowed_user_ids),
        allowed_actions: uniqueStrings(form.allowed_actions),
        public_config: {
            allowed_updates: uniqueStrings(form.allowed_updates),
            bot_username: form.bot_username.trim() || null,
            description: form.description.trim() || null,
            chat_history_limit: Number(form.chat_history_limit || 30),
            chat_session_timeline_limit: Number(form.chat_session_timeline_limit || 6),
        },
    };

    if (Object.keys(secretConfig).length > 0) {
        payload.secret_config = secretConfig;
    }

    return payload;
}

/**
 * Build the editor state used for runtime webhook operations.
 *
 * @param {Record<string, any> | null} webhookPayload
 * @returns {{url: string, drop_pending_updates: boolean}}
 */
export function buildWebhookForm(webhookPayload) {
    return {
        url: webhookPayload?.result?.url ?? '',
        drop_pending_updates: false,
    };
}

/**
 * Build one concise scope line for the list panel.
 *
 * @param {Record<string, any>} bot
 * @param {(key: string) => string} t
 * @returns {string}
 */
export function buildBotSummary(bot, t) {
    const parts = [
        t(`adminTelegramBots.classification.purpose.${bot.purpose ?? 'remote_control'}`),
        t(`adminTelegramBots.classification.environment.${bot.environment ?? 'local'}`),
    ];

    if (bot.machine_group) {
        parts.push(bot.machine_group);
    }

    return parts.join(' · ');
}

/**
 * Format one audit timestamp for the selected locale.
 *
 * @param {string | null | undefined} value
 * @param {string} locale
 * @returns {string}
 */
export function formatAuditTimestamp(value, locale) {
    if (!value) {
        return '-';
    }

    const timestamp = new Date(value);

    if (Number.isNaN(timestamp.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale === 'vi' ? 'vi-VN' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(timestamp);
}

/**
 * Toggle one option inside a multi-select list.
 *
 * @param {string[]} list
 * @param {string} value
 * @returns {string[]}
 */
export function toggleSelection(list, value) {
    return list.includes(value) ? list.filter((item) => item !== value) : [...list, value];
}

/**
 * Convert one snake_case key into a human fallback label.
 *
 * @param {string} value
 * @returns {string}
 */
export function formatOptionLabel(value) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

/**
 * Build a normalized search string once so filtering does not repeatedly lowercase bot fields.
 *
 * @param {Record<string, any>} bot
 * @returns {string}
 */
export function buildBotSearchIndex(bot) {
    return [
        bot.display_name,
        bot.key,
        bot.public_config?.bot_username,
        bot.machine_group,
        bot.environment,
        bot.purpose,
    ]
        .filter(Boolean)
        .map((value) => String(value).toLowerCase())
        .join(' ');
}

/**
 * Filter bots using the active search and segmented selectors.
 *
 * @param {Record<string, any>[]} bots
 * @param {{search: string, environment: string, purpose: string, status: string}} filters
 * @returns {Record<string, any>[]}
 */
export function filterBots(bots, filters) {
    const normalizedSearch = filters.search.trim().toLowerCase();

    return bots.filter((bot) => {
        if (filters.environment && bot.environment !== filters.environment) {
            return false;
        }

        if (filters.purpose && bot.purpose !== filters.purpose) {
            return false;
        }

        if (filters.status === 'enabled' && !bot.enabled) {
            return false;
        }

        if (filters.status === 'disabled' && bot.enabled) {
            return false;
        }

        if (filters.status === 'default' && !bot.is_default) {
            return false;
        }

        if (normalizedSearch === '') {
            return true;
        }

        return (bot.search_index ?? buildBotSearchIndex(bot)).includes(normalizedSearch);
    });
}

/**
 * Slice one bot collection into a list page.
 *
 * @param {Record<string, any>[]} bots
 * @param {number} page
 * @returns {{
 *   items: Record<string, any>[],
 *   totalPages: number,
 *   safePage: number,
 *   firstItem: number,
 *   lastItem: number,
 * }}
 */
export function paginateBots(bots, page) {
    const totalPages = Math.max(1, Math.ceil(bots.length / BOT_LIST_PAGE_SIZE));
    const safePage = Math.min(Math.max(page, 1), totalPages);
    const start = (safePage - 1) * BOT_LIST_PAGE_SIZE;
    const items = bots.slice(start, start + BOT_LIST_PAGE_SIZE);

    return {
        items,
        totalPages,
        safePage,
        firstItem: bots.length === 0 ? 0 : start + 1,
        lastItem: Math.min(start + items.length, bots.length),
    };
}

/**
 * Flatten metadata into one readable label/value list.
 *
 * @param {Record<string, any> | null | undefined} metadata
 * @returns {{label: string, value: string}[]}
 */
export function buildMetadataRows(metadata) {
    if (!metadata || typeof metadata !== 'object') {
        return [];
    }

    return Object.entries(metadata)
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .map(([label, value]) => ({
            label: formatOptionLabel(label),
            value: typeof value === 'string' ? value : JSON.stringify(value),
        }));
}

/**
 * Collapse one string array into unique trimmed values.
 *
 * @param {string[]} values
 * @returns {string[]}
 */
function uniqueStrings(values) {
    return values
        .map((value) => String(value).trim())
        .filter(Boolean)
        .filter((value, index, array) => array.indexOf(value) === index);
}
