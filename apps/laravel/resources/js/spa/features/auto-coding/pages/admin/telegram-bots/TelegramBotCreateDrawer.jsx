import { memo, useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import api from '../../../../../lib/api';
import AppIcon from '../../../../../components/icons/AppIcon';
import SensitiveInput from '../../../../auth/components/SensitiveInput';
import TelegramBotChipSelect from './TelegramBotChipSelect';
import TelegramBotTagInput from './TelegramBotTagInput';
import {
    BOT_ENVIRONMENT_OPTIONS,
    BOT_LOCALE_OPTIONS,
    BOT_PURPOSE_OPTIONS,
    TELEGRAM_ACTION_OPTIONS,
    TELEGRAM_CREATE_DEFAULT_ACTIONS,
    TELEGRAM_UPDATE_OPTIONS,
} from './telegramBotAdmin.helpers';

const createStepIcons = ['bot', 'shield', 'link'];

function normalizeBotKey(value) {
    return value
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 64);
}

function buildSuggestedBotKey(displayName) {
    return normalizeBotKey(displayName);
}

function generateWebhookSecret() {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    const bytes = new Uint8Array(32);

    if (typeof window !== 'undefined' && window.crypto?.getRandomValues) {
        window.crypto.getRandomValues(bytes);
    } else {
        for (let index = 0; index < bytes.length; index += 1) {
            bytes[index] = Math.floor(Math.random() * 256);
        }
    }

    return Array.from(bytes, (byte) => alphabet[byte % alphabet.length]).join('');
}

function normalizeCandidateId(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).trim();
}

function TelegramBotCreatePreview({ t, form }) {
    const previewName =
        form.display_name.trim() || t('adminTelegramBots.createPreview.nameFallback');
    const previewKey = form.key.trim() || t('adminTelegramBots.createPreview.keyFallback');
    const machineGroup =
        form.machine_group.trim() || t('adminTelegramBots.createPreview.machineFallback');
    const description =
        form.description.trim() || t('adminTelegramBots.createPreview.descriptionFallback');
    const previewBadges = [
        {
            key: 'runtime',
            label: form.enabled
                ? t('adminTelegramBots.status.enabled')
                : t('adminTelegramBots.status.disabled'),
            tone: form.enabled ? 'success' : 'neutral',
        },
        ...(form.is_default
            ? [
                  {
                      key: 'default',
                      label: t('adminTelegramBots.status.defaultShort'),
                      tone: form.enabled ? 'success' : 'warning',
                  },
              ]
            : []),
    ];
    const safetyItems = [
        {
            icon: form.enabled ? 'play' : 'lock',
            text: form.enabled
                ? t('adminTelegramBots.createPreview.enabledDraft')
                : t('adminTelegramBots.fieldHelp.enabled'),
            tone: form.enabled ? 'warning' : 'success',
        },
        {
            icon: form.is_default ? 'target' : 'shield',
            text: form.is_default
                ? t('adminTelegramBots.createPreview.defaultDraft')
                : t('adminTelegramBots.fieldHelp.defaultBot'),
            tone: form.is_default ? 'warning' : 'neutral',
        },
        ...(form.is_default && !form.enabled
            ? [
                  {
                      icon: 'info',
                      text: t('adminTelegramBots.createPreview.defaultDisabledDraft'),
                      tone: 'warning',
                  },
              ]
            : []),
    ];

    return (
        <aside
            className="admin-telegram-bots__create-preview"
            aria-label={t('adminTelegramBots.createPreview.title')}
        >
            <div className="admin-telegram-bots__create-preview-card">
                <span className="admin-telegram-bots__create-preview-icon">
                    <AppIcon name="bot" />
                </span>
                <div>
                    <p className="admin-telegram-bots__section-eyebrow">
                        {t('adminTelegramBots.createPreview.eyebrow')}
                    </p>
                    <h4>{previewName}</h4>
                    <code>{previewKey}</code>
                    <div className="admin-telegram-bots__create-preview-badges">
                        {previewBadges.map((badge) => (
                            <span className={`is-${badge.tone}`} key={badge.key}>
                                {badge.label}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
            <dl className="admin-telegram-bots__create-preview-list">
                <div>
                    <dt>{t('adminTelegramBots.fields.purpose')}</dt>
                    <dd>{t(`adminTelegramBots.classification.purpose.${form.purpose}`)}</dd>
                </div>
                <div>
                    <dt>{t('adminTelegramBots.fields.environment')}</dt>
                    <dd>{t(`adminTelegramBots.classification.environment.${form.environment}`)}</dd>
                </div>
                <div>
                    <dt>{t('adminTelegramBots.fields.machineGroup')}</dt>
                    <dd>{machineGroup}</dd>
                </div>
                <div>
                    <dt>{t('adminTelegramBots.fields.locale')}</dt>
                    <dd>{form.locale === 'vi' ? t('common.vietnamese') : t('common.english')}</dd>
                </div>
                <div className="admin-telegram-bots__create-preview-description">
                    <dt>{t('adminTelegramBots.fields.description')}</dt>
                    <dd>{description}</dd>
                </div>
            </dl>

            <div className="admin-telegram-bots__create-next">
                <h5>{t('adminTelegramBots.createPreview.nextTitle')}</h5>
                <ul>
                    {[0, 1, 2].map((index) => (
                        <li key={index}>
                            <AppIcon name={index === 0 ? 'lock' : index === 1 ? 'users' : 'link'} />
                            <span>{t(`adminTelegramBots.createPreview.nextItems.${index}`)}</span>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="admin-telegram-bots__create-safety">
                <h5>{t('adminTelegramBots.createPreview.safetyTitle')}</h5>
                {safetyItems.map((item) => (
                    <div
                        className={`admin-telegram-bots__create-safety-item is-${item.tone}`}
                        key={item.text}
                    >
                        <AppIcon name={item.icon} />
                        <span>{item.text}</span>
                    </div>
                ))}
            </div>
        </aside>
    );
}

function TelegramBotCreateSteps({ t }) {
    return (
        <div className="admin-telegram-bots__create-steps">
            {createStepIcons.map((iconName, index) => (
                <div className="admin-telegram-bots__create-step" key={iconName}>
                    <span>
                        <AppIcon name={iconName} />
                    </span>
                    <strong>{t(`adminTelegramBots.createSteps.${index}.title`)}</strong>
                    <small>{t(`adminTelegramBots.createSteps.${index}.text`)}</small>
                </div>
            ))}
        </div>
    );
}

function TelegramBotCreateStatus({ t, form }) {
    const requiredFields = [
        {
            key: 'key',
            label: t('adminTelegramBots.fields.key'),
            isComplete: form.key.trim() !== '',
        },
        {
            key: 'display_name',
            label: t('adminTelegramBots.fields.displayName'),
            isComplete: form.display_name.trim() !== '',
        },
        ...(form.enabled
            ? [
                  {
                      key: 'bot_token',
                      label: t('adminTelegramBots.fields.botToken'),
                      isComplete: form.bot_token.trim() !== '',
                  },
                  {
                      key: 'allowed_operator',
                      label: t('adminTelegramBots.fields.allowedOperator'),
                      isComplete:
                          form.allowed_chat_ids.length > 0 || form.allowed_user_ids.length > 0,
                  },
              ]
            : []),
    ];
    const missingFields = requiredFields.filter((field) => !field.isComplete);
    const completeCount = requiredFields.length - missingFields.length;
    const completionPercent = Math.round((completeCount / requiredFields.length) * 100);
    const safetyWarnings = [
        form.enabled ? t('adminTelegramBots.createStatus.safety.enabled') : '',
        form.is_default ? t('adminTelegramBots.createStatus.safety.defaultBot') : '',
        form.is_default && !form.enabled
            ? t('adminTelegramBots.createStatus.safety.defaultDisabled')
            : '',
    ].filter(Boolean);
    const hasSafetyWarning = safetyWarnings.length > 0;
    const statusChecks = (
        <div className="admin-telegram-bots__create-status-checks">
            {requiredFields.map((field) => (
                <span className={field.isComplete ? 'is-complete' : ''} key={field.key}>
                    <AppIcon name={field.isComplete ? 'check' : 'info'} />
                    {field.label}
                </span>
            ))}
        </div>
    );

    if (missingFields.length === 0 && !hasSafetyWarning) {
        return (
            <div className="admin-telegram-bots__create-status is-ready" role="status">
                <AppIcon name="check" />
                <div>
                    <strong>{t('adminTelegramBots.createStatus.readyTitle')}</strong>
                    <span>{t('adminTelegramBots.createStatus.readyText')}</span>
                    <div
                        className="admin-telegram-bots__create-status-progress"
                        role="progressbar"
                        aria-label={t('adminTelegramBots.createStatus.requiredProgress')}
                        aria-valuemin={0}
                        aria-valuemax={requiredFields.length}
                        aria-valuenow={completeCount}
                    >
                        <span style={{ width: `${completionPercent}%` }} />
                    </div>
                    <small className="admin-telegram-bots__create-status-count">
                        {completeCount}/{requiredFields.length}{' '}
                        {t('adminTelegramBots.createStatus.requiredComplete')}
                    </small>
                    {statusChecks}
                </div>
            </div>
        );
    }

    return (
        <div className="admin-telegram-bots__create-status is-warning" role="status">
            <AppIcon name="info" />
            <div>
                <strong>{t('adminTelegramBots.createStatus.reviewTitle')}</strong>
                {missingFields.length > 0 ? (
                    <span>
                        {t('adminTelegramBots.createStatus.missingFields')}:{' '}
                        {missingFields.map((field) => field.label).join(', ')}
                    </span>
                ) : null}
                {safetyWarnings.map((warning) => (
                    <span key={warning}>{warning}</span>
                ))}
                <div
                    className="admin-telegram-bots__create-status-progress"
                    role="progressbar"
                    aria-label={t('adminTelegramBots.createStatus.requiredProgress')}
                    aria-valuemin={0}
                    aria-valuemax={requiredFields.length}
                    aria-valuenow={completeCount}
                >
                    <span style={{ width: `${completionPercent}%` }} />
                </div>
                <small className="admin-telegram-bots__create-status-count">
                    {completeCount}/{requiredFields.length}{' '}
                    {t('adminTelegramBots.createStatus.requiredComplete')}
                </small>
                {statusChecks}
            </div>
        </div>
    );
}

/**
 * Render concise setup steps inside the add-bot drawer.
 *
 * @param {{ t: (key: string) => any }} props
 * @returns {import('react').JSX.Element}
 */
function TelegramBotCreateGuideTab({ t }) {
    const guideSections = t('adminTelegramBots.setupGuide.sections');

    return (
        <section
            className="admin-telegram-bots__create-guide"
            aria-label={t('adminTelegramBots.setupGuide.title')}
        >
            <header className="admin-telegram-bots__create-guide-head">
                <span>
                    <AppIcon name="info" />
                </span>
                <div>
                    <h4>{t('adminTelegramBots.setupGuide.title')}</h4>
                    <p>{t('adminTelegramBots.setupGuide.text')}</p>
                </div>
            </header>
            <div className="admin-telegram-bots__create-guide-grid">
                {Array.isArray(guideSections)
                    ? guideSections.map((section) => (
                          <article
                              key={section.title}
                              className="admin-telegram-bots__create-guide-card"
                          >
                              <h5>{section.title}</h5>
                              <ol>
                                  {section.steps.map((step) => (
                                      <li key={step}>
                                          <span>{step}</span>
                                      </li>
                                  ))}
                              </ol>
                          </article>
                      ))
                    : null}
            </div>
        </section>
    );
}

/**
 * Render the add-bot drawer used to create one new Telegram bot entry.
 *
 * @param {{
 *   open: boolean,
 *   t: (key: string) => string,
 *   form: Record<string, any>,
 *   initialLocale?: string,
 *   creating: boolean,
 *   onClose: () => void,
 *   onChange: (field: string, value: any) => void,
 *   onSubmit: (event: import('react').FormEvent<HTMLFormElement>) => void,
 * }} props
 * @returns {import('react').ReactPortal | null}
 */
function TelegramBotCreateDrawer({
    open,
    t,
    form,
    initialLocale = 'en',
    creating,
    onClose,
    onChange,
    onSubmit,
}) {
    const [activeTab, setActiveTab] = useState('config');
    const [idLookupState, setIdLookupState] = useState({
        loading: false,
        error: '',
        needsWebhookDelete: false,
        chats: [],
        users: [],
    });
    const hasDraftChanges =
        form.key.trim() !== '' ||
        form.display_name.trim() !== '' ||
        form.machine_group.trim() !== '' ||
        form.description.trim() !== '' ||
        form.bot_token.trim() !== '' ||
        form.webhook_secret.trim() !== '' ||
        form.allowed_chat_ids.length > 0 ||
        form.allowed_user_ids.length > 0 ||
        JSON.stringify(form.allowed_actions) !== JSON.stringify(TELEGRAM_CREATE_DEFAULT_ACTIONS) ||
        form.allowed_updates.length !== TELEGRAM_UPDATE_OPTIONS.length ||
        form.purpose !== 'remote_control' ||
        form.environment !== 'local' ||
        form.locale !== initialLocale ||
        form.enabled ||
        form.is_default;
    const requestClose = useCallback(() => {
        if (creating) {
            return;
        }

        if (
            hasDraftChanges &&
            typeof window !== 'undefined' &&
            !window.confirm(t('adminTelegramBots.createUnsavedConfirm'))
        ) {
            return;
        }

        onClose();
    }, [creating, hasDraftChanges, onClose, t]);

    useEffect(() => {
        if (!open || typeof document === 'undefined') {
            return undefined;
        }

        const { body, documentElement } = document;
        const previousBodyOverflow = body.style.overflow;
        const previousHtmlOverflow = documentElement.style.overflow;

        const handleKeyDown = (event) => {
            if (event.key === 'Escape' && !creating) {
                requestClose();
            }
        };

        body.style.overflow = 'hidden';
        documentElement.style.overflow = 'hidden';
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            body.style.overflow = previousBodyOverflow;
            documentElement.style.overflow = previousHtmlOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [creating, open, requestClose]);

    const handleDrawerClick = useCallback((event) => {
        event.stopPropagation();
    }, []);
    const handleBackdropClick = useCallback(() => {
        requestClose();
    }, [requestClose]);

    const handleKeyChange = useCallback(
        (event) => onChange('key', normalizeBotKey(event.target.value)),
        [onChange],
    );
    const handleDisplayNameChange = useCallback(
        (event) => onChange('display_name', event.target.value),
        [onChange],
    );
    const handlePurposeChange = useCallback(
        (event) => onChange('purpose', event.target.value),
        [onChange],
    );
    const handleEnvironmentChange = useCallback(
        (event) => onChange('environment', event.target.value),
        [onChange],
    );
    const handleMachineGroupChange = useCallback(
        (event) => onChange('machine_group', event.target.value),
        [onChange],
    );
    const handleDescriptionChange = useCallback(
        (event) => onChange('description', event.target.value),
        [onChange],
    );
    const handleLocaleChange = useCallback(
        (event) => onChange('locale', event.target.value),
        [onChange],
    );
    const handleEnabledChange = useCallback(
        (event) => onChange('enabled', event.target.checked),
        [onChange],
    );
    const handleDefaultChange = useCallback(
        (event) => onChange('is_default', event.target.checked),
        [onChange],
    );
    const handleBotTokenChange = useCallback(
        (event) => onChange('bot_token', event.target.value),
        [onChange],
    );
    const handleWebhookSecretChange = useCallback(
        (event) => onChange('webhook_secret', event.target.value),
        [onChange],
    );
    const handleGenerateWebhookSecret = useCallback(() => {
        onChange('webhook_secret', generateWebhookSecret());
    }, [onChange]);
    const inspectChatIds = useCallback(
        async (deleteWebhook = false) => {
            if (form.bot_token.trim() === '' || creating) {
                setIdLookupState((current) => ({
                    ...current,
                    error: t('adminTelegramBots.idHelper.tokenRequired'),
                    needsWebhookDelete: false,
                }));

                return;
            }

            setIdLookupState({
                loading: true,
                error: '',
                needsWebhookDelete: false,
                chats: [],
                users: [],
            });

            try {
                const response = await api.post(
                    '/admin/auto-coding/telegram-bots/inspect-chat-ids',
                    {
                        bot_token: form.bot_token,
                        delete_webhook: deleteWebhook,
                    },
                    { timeoutMs: 20000 },
                );
                const payload = response.data?.data ?? {};
                const chats = Array.isArray(payload.chats) ? payload.chats : [];
                const users = Array.isArray(payload.users) ? payload.users : [];
                const hasCandidates = chats.length > 0 || users.length > 0;

                setIdLookupState({
                    loading: false,
                    error:
                        payload.ok === false
                            ? payload.description || t('adminTelegramBots.idHelper.lookupError')
                            : !hasCandidates
                              ? t('adminTelegramBots.idHelper.noResults')
                              : '',
                    needsWebhookDelete: payload.needs_webhook_delete === true,
                    chats,
                    users,
                });
            } catch (requestError) {
                setIdLookupState({
                    loading: false,
                    error:
                        requestError?.response?.data?.message ||
                        requestError?.data?.message ||
                        t('adminTelegramBots.idHelper.lookupError'),
                    needsWebhookDelete: false,
                    chats: [],
                    users: [],
                });
            }
        },
        [creating, form.bot_token, t],
    );
    const handleInspectChatIds = useCallback(() => {
        inspectChatIds(false);
    }, [inspectChatIds]);
    const handleDeleteWebhookAndInspectChatIds = useCallback(() => {
        inspectChatIds(true);
    }, [inspectChatIds]);
    const handleAddLookupChatId = useCallback(
        (event) => {
            const id = normalizeCandidateId(event.currentTarget.dataset.id);

            if (id !== '' && !form.allowed_chat_ids.includes(id)) {
                onChange('allowed_chat_ids', [...form.allowed_chat_ids, id]);
            }
        },
        [form.allowed_chat_ids, onChange],
    );
    const handleAddLookupUserId = useCallback(
        (event) => {
            const id = normalizeCandidateId(event.currentTarget.dataset.id);

            if (id !== '' && !form.allowed_user_ids.includes(id)) {
                onChange('allowed_user_ids', [...form.allowed_user_ids, id]);
            }
        },
        [form.allowed_user_ids, onChange],
    );
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
    const isKeyMissing = form.key.trim() === '';
    const isDisplayNameMissing = form.display_name.trim() === '';
    const isEnabledTokenMissing = form.enabled && form.bot_token.trim() === '';
    const isEnabledAccessMissing =
        form.enabled && form.allowed_chat_ids.length === 0 && form.allowed_user_ids.length === 0;
    const keyLength = form.key.length;
    const displayNameLength = form.display_name.length;
    const descriptionLength = form.description.length;
    const suggestedKey = buildSuggestedBotKey(form.display_name);
    const canUseSuggestedKey = suggestedKey !== '' && form.key.trim() !== suggestedKey && !creating;
    const submitDisabled =
        creating ||
        isKeyMissing ||
        isDisplayNameMissing ||
        isEnabledTokenMissing ||
        isEnabledAccessMissing;
    const submitTitle = submitDisabled
        ? t('adminTelegramBots.createStatus.submitDisabled')
        : t('adminTelegramBots.createButton');
    const idHelperSteps = t('adminTelegramBots.idHelper.steps');
    const hasLookupResults = idLookupState.chats.length > 0 || idLookupState.users.length > 0;
    const handleUseSuggestedKey = useCallback(() => {
        if (suggestedKey !== '') {
            onChange('key', suggestedKey);
        }
    }, [onChange, suggestedKey]);
    const handleConfigTab = useCallback(() => {
        setActiveTab('config');
    }, []);
    const handleGuideTab = useCallback(() => {
        setActiveTab('guide');
    }, []);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            className="admin-telegram-bots__drawer-backdrop"
            role="presentation"
            onClick={handleBackdropClick}
        >
            <aside
                className="admin-telegram-bots__drawer"
                role="dialog"
                aria-modal="true"
                aria-labelledby="telegram-bot-create-drawer-title"
                aria-busy={creating}
                onClick={handleDrawerClick}
            >
                <div className="admin-telegram-bots__drawer-head">
                    <div>
                        <p className="admin-telegram-bots__section-eyebrow">
                            {t('adminTelegramBots.createEyebrow')}
                        </p>
                        <h3 id="telegram-bot-create-drawer-title">
                            {t('adminTelegramBots.createTitle')}
                        </h3>
                        <p>{t('adminTelegramBots.createText')}</p>
                    </div>
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        disabled={creating}
                        onClick={requestClose}
                        title={t('common.cancel')}
                    >
                        <AppIcon name="x" />
                        {t('common.cancel')}
                    </button>
                </div>

                <div className="admin-telegram-bots__create-tabs" role="tablist">
                    <button
                        type="button"
                        className={activeTab === 'config' ? 'is-active' : ''}
                        role="tab"
                        aria-selected={activeTab === 'config'}
                        onClick={handleConfigTab}
                    >
                        <AppIcon name="edit" />
                        {t('adminTelegramBots.createTabs.config')}
                    </button>
                    <button
                        type="button"
                        className={activeTab === 'guide' ? 'is-active' : ''}
                        role="tab"
                        aria-selected={activeTab === 'guide'}
                        onClick={handleGuideTab}
                    >
                        <AppIcon name="info" />
                        {t('adminTelegramBots.createTabs.guide')}
                    </button>
                </div>

                {activeTab === 'guide' ? <TelegramBotCreateGuideTab t={t} /> : null}

                <form
                    className={`admin-telegram-bots__drawer-form ${activeTab === 'guide' ? 'is-hidden' : ''}`}
                    onSubmit={onSubmit}
                >
                    <TelegramBotCreateSteps t={t} />

                    <div className="admin-telegram-bots__create-layout">
                        <section className="admin-telegram-bots__create-form-panel">
                            <header className="admin-telegram-bots__create-section-head">
                                <h4>{t('adminTelegramBots.createSections.profile')}</h4>
                                <p>{t('adminTelegramBots.createSections.profileText')}</p>
                            </header>

                            <div className="admin-telegram-bots__detail-grid">
                                <div
                                    className={`admin-telegram-bots__field ${
                                        isKeyMissing ? 'is-required-missing' : ''
                                    }`}
                                >
                                    <label htmlFor="telegram-bot-create-key">
                                        {t('adminTelegramBots.fields.key')}
                                        <em>{t('adminTelegramBots.createStatus.required')}</em>
                                    </label>
                                    <input
                                        id="telegram-bot-create-key"
                                        className="app-input"
                                        type="text"
                                        value={form.key}
                                        onChange={handleKeyChange}
                                        placeholder={t('adminTelegramBots.placeholders.key')}
                                        required
                                        disabled={creating}
                                        autoComplete="off"
                                        maxLength={64}
                                        autoFocus
                                        aria-invalid={isKeyMissing}
                                        aria-describedby="telegram-bot-create-key-help"
                                    />
                                    {canUseSuggestedKey ? (
                                        <button
                                            type="button"
                                            className="admin-telegram-bots__field-suggestion"
                                            onClick={handleUseSuggestedKey}
                                            title={t(
                                                'adminTelegramBots.createPreview.useSuggestedKey',
                                            )}
                                        >
                                            <AppIcon name="check" />
                                            {t(
                                                'adminTelegramBots.createPreview.suggestedKey',
                                            )}: <code>{suggestedKey}</code>
                                        </button>
                                    ) : null}
                                    <span className="admin-telegram-bots__field-help-row">
                                        <small id="telegram-bot-create-key-help">
                                            {t('adminTelegramBots.fieldHelp.key')}
                                        </small>
                                        <small aria-label="Bot key length">{keyLength}/64</small>
                                    </span>
                                </div>

                                <label
                                    className={`admin-telegram-bots__field ${
                                        isDisplayNameMissing ? 'is-required-missing' : ''
                                    }`}
                                >
                                    <span>
                                        {t('adminTelegramBots.fields.displayName')}
                                        <em>{t('adminTelegramBots.createStatus.required')}</em>
                                    </span>
                                    <input
                                        id="telegram-bot-create-display-name"
                                        className="app-input"
                                        type="text"
                                        value={form.display_name}
                                        onChange={handleDisplayNameChange}
                                        placeholder={t(
                                            'adminTelegramBots.placeholders.displayName',
                                        )}
                                        required
                                        disabled={creating}
                                        autoComplete="off"
                                        maxLength={120}
                                        aria-invalid={isDisplayNameMissing}
                                        aria-describedby="telegram-bot-create-display-name-help"
                                    />
                                    <span className="admin-telegram-bots__field-help-row">
                                        <small id="telegram-bot-create-display-name-help">
                                            {t('adminTelegramBots.fieldHelp.displayName')}
                                        </small>
                                        <small aria-label="Display name length">
                                            {displayNameLength}/120
                                        </small>
                                    </span>
                                </label>

                                <label className="admin-telegram-bots__field">
                                    <span>{t('adminTelegramBots.fields.purpose')}</span>
                                    <select
                                        id="telegram-bot-create-purpose"
                                        className="app-input"
                                        value={form.purpose}
                                        onChange={handlePurposeChange}
                                        disabled={creating}
                                    >
                                        {BOT_PURPOSE_OPTIONS.map((purpose) => (
                                            <option value={purpose} key={purpose}>
                                                {t(
                                                    `adminTelegramBots.classification.purpose.${purpose}`,
                                                )}
                                            </option>
                                        ))}
                                    </select>
                                    <small>{t('adminTelegramBots.fieldHelp.purpose')}</small>
                                </label>

                                <label className="admin-telegram-bots__field">
                                    <span>{t('adminTelegramBots.fields.environment')}</span>
                                    <select
                                        id="telegram-bot-create-environment"
                                        className="app-input"
                                        value={form.environment}
                                        onChange={handleEnvironmentChange}
                                        disabled={creating}
                                    >
                                        {BOT_ENVIRONMENT_OPTIONS.map((environment) => (
                                            <option value={environment} key={environment}>
                                                {t(
                                                    `adminTelegramBots.classification.environment.${environment}`,
                                                )}
                                            </option>
                                        ))}
                                    </select>
                                    <small>{t('adminTelegramBots.fieldHelp.environment')}</small>
                                </label>

                                <label className="admin-telegram-bots__field">
                                    <span>{t('adminTelegramBots.fields.machineGroup')}</span>
                                    <input
                                        id="telegram-bot-create-machine-group"
                                        className="app-input"
                                        type="text"
                                        value={form.machine_group}
                                        onChange={handleMachineGroupChange}
                                        placeholder={t(
                                            'adminTelegramBots.placeholders.machineGroup',
                                        )}
                                        disabled={creating}
                                        autoComplete="off"
                                        maxLength={80}
                                    />
                                    <small>{t('adminTelegramBots.fieldHelp.machineGroup')}</small>
                                </label>

                                <label className="admin-telegram-bots__field">
                                    <span>{t('adminTelegramBots.fields.locale')}</span>
                                    <select
                                        id="telegram-bot-create-locale"
                                        className="app-input"
                                        value={form.locale}
                                        onChange={handleLocaleChange}
                                        disabled={creating}
                                    >
                                        {BOT_LOCALE_OPTIONS.map((locale) => (
                                            <option value={locale} key={locale}>
                                                {locale === 'vi'
                                                    ? t('common.vietnamese')
                                                    : t('common.english')}
                                            </option>
                                        ))}
                                    </select>
                                    <small>{t('adminTelegramBots.fieldHelp.locale')}</small>
                                </label>

                                <label className="admin-telegram-bots__field admin-telegram-bots__field--full">
                                    <span>{t('adminTelegramBots.fields.description')}</span>
                                    <textarea
                                        id="telegram-bot-create-description"
                                        className="app-input"
                                        value={form.description}
                                        onChange={handleDescriptionChange}
                                        placeholder={t(
                                            'adminTelegramBots.placeholders.description',
                                        )}
                                        disabled={creating}
                                        maxLength={240}
                                    />
                                    <span className="admin-telegram-bots__field-help-row">
                                        <small>
                                            {t('adminTelegramBots.fieldHelp.description')}
                                        </small>
                                        <small aria-label="Description length">
                                            {descriptionLength}/240
                                        </small>
                                    </span>
                                </label>
                            </div>
                        </section>

                        <TelegramBotCreatePreview t={t} form={form} />
                    </div>

                    <section className="admin-telegram-bots__create-form-panel">
                        <header className="admin-telegram-bots__create-section-head">
                            <h4>{t('adminTelegramBots.createSections.activation')}</h4>
                            <p>{t('adminTelegramBots.createSections.activationText')}</p>
                        </header>

                        <div className="admin-telegram-bots__toggle-grid">
                            <label
                                className={`admin-telegram-bots__checkbox-card ${
                                    form.enabled ? 'is-checked is-runtime' : 'is-recommended'
                                }`}
                            >
                                <input
                                    id="telegram-bot-create-enabled"
                                    type="checkbox"
                                    checked={form.enabled}
                                    onChange={handleEnabledChange}
                                    disabled={creating}
                                />
                                <span>
                                    <strong>
                                        {t('adminTelegramBots.fields.enabled')}
                                        {!form.enabled ? (
                                            <em>
                                                {t('adminTelegramBots.createStatus.recommended')}
                                            </em>
                                        ) : null}
                                    </strong>
                                    <small>{t('adminTelegramBots.fieldHelp.enabled')}</small>
                                </span>
                            </label>
                            <label
                                className={`admin-telegram-bots__checkbox-card ${
                                    form.is_default ? 'is-checked' : ''
                                } ${form.is_default && !form.enabled ? 'is-warning' : ''}`}
                            >
                                <input
                                    id="telegram-bot-create-default"
                                    type="checkbox"
                                    checked={form.is_default}
                                    onChange={handleDefaultChange}
                                    disabled={creating}
                                />
                                <span>
                                    <strong>
                                        {t('adminTelegramBots.fields.defaultBot')}
                                        {form.is_default && !form.enabled ? (
                                            <em>
                                                {t('adminTelegramBots.createStatus.needsReview')}
                                            </em>
                                        ) : null}
                                    </strong>
                                    <small>
                                        {form.is_default && !form.enabled
                                            ? t(
                                                  'adminTelegramBots.createStatus.safety.defaultDisabled',
                                              )
                                            : t('adminTelegramBots.fieldHelp.defaultBot')}
                                    </small>
                                </span>
                            </label>
                        </div>
                    </section>

                    <section className="admin-telegram-bots__create-form-panel">
                        <header className="admin-telegram-bots__create-section-head">
                            <h4>{t('adminTelegramBots.createSections.security')}</h4>
                            <p>{t('adminTelegramBots.createSections.securityText')}</p>
                        </header>

                        <div className="admin-telegram-bots__detail-grid">
                            <label
                                className={`admin-telegram-bots__field admin-telegram-bots__field--full ${
                                    isEnabledTokenMissing ? 'is-required-missing' : ''
                                }`}
                            >
                                <span>
                                    {t('adminTelegramBots.fields.botToken')}
                                    {form.enabled ? (
                                        <em>{t('adminTelegramBots.createStatus.required')}</em>
                                    ) : null}
                                </span>
                                <SensitiveInput
                                    id="telegram-bot-create-token"
                                    value={form.bot_token}
                                    onChange={handleBotTokenChange}
                                    placeholder={t('adminTelegramBots.placeholders.botToken')}
                                    disabled={creating}
                                    required={form.enabled}
                                    invalid={isEnabledTokenMissing}
                                    autoComplete="new-password"
                                    revealLabel={t('adminTelegramBots.revealSecret')}
                                    concealLabel={t('adminTelegramBots.concealSecret')}
                                />
                                <small>{t('adminTelegramBots.fieldHelp.botToken')}</small>
                            </label>

                            <label className="admin-telegram-bots__field admin-telegram-bots__field--full">
                                <span>{t('adminTelegramBots.fields.webhookSecret')}</span>
                                <div className="admin-telegram-bots__field-action-row">
                                    <SensitiveInput
                                        id="telegram-bot-create-webhook-secret"
                                        value={form.webhook_secret}
                                        onChange={handleWebhookSecretChange}
                                        placeholder={t(
                                            'adminTelegramBots.placeholders.webhookSecret',
                                        )}
                                        disabled={creating}
                                        autoComplete="new-password"
                                        revealLabel={t('adminTelegramBots.revealSecret')}
                                        concealLabel={t('adminTelegramBots.concealSecret')}
                                    />
                                    <button
                                        type="button"
                                        className="app-button app-button--ghost admin-telegram-bots__inline-tool-button"
                                        disabled={creating}
                                        onClick={handleGenerateWebhookSecret}
                                        title={t('adminTelegramBots.generateWebhookSecret')}
                                    >
                                        <AppIcon name="refresh" />
                                        <span>{t('adminTelegramBots.generateWebhookSecret')}</span>
                                    </button>
                                </div>
                                <small>{t('adminTelegramBots.fieldHelp.webhookSecret')}</small>
                            </label>
                        </div>
                    </section>

                    <section className="admin-telegram-bots__create-form-panel">
                        <header className="admin-telegram-bots__create-section-head">
                            <h4>{t('adminTelegramBots.sections.accessControl')}</h4>
                            <p>{t('adminTelegramBots.sections.accessControlText')}</p>
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
                        <div className="admin-telegram-bots__id-helper">
                            <span>
                                <AppIcon name="info" />
                            </span>
                            <div>
                                <strong>{t('adminTelegramBots.idHelper.title')}</strong>
                                <ol>
                                    {Array.isArray(idHelperSteps)
                                        ? idHelperSteps.map((step) => <li key={step}>{step}</li>)
                                        : null}
                                </ol>
                            </div>
                            <button
                                type="button"
                                className="app-button app-button--ghost admin-telegram-bots__inline-tool-button"
                                disabled={
                                    creating ||
                                    idLookupState.loading ||
                                    form.bot_token.trim() === ''
                                }
                                onClick={handleInspectChatIds}
                                title={t('adminTelegramBots.idHelper.lookupButton')}
                            >
                                <AppIcon name={idLookupState.loading ? 'refresh' : 'search'} />
                                <span>
                                    {idLookupState.loading
                                        ? t('adminTelegramBots.idHelper.lookupLoading')
                                        : t('adminTelegramBots.idHelper.lookupButton')}
                                </span>
                            </button>
                        </div>
                        {idLookupState.error ? (
                            <div className="admin-telegram-bots__id-helper-error">
                                <span>
                                    <AppIcon name="info" />
                                    {idLookupState.error}
                                </span>
                                {idLookupState.needsWebhookDelete ? (
                                    <button
                                        type="button"
                                        className="app-button app-button--ghost admin-telegram-bots__inline-tool-button"
                                        disabled={creating || idLookupState.loading}
                                        onClick={handleDeleteWebhookAndInspectChatIds}
                                        title={t('adminTelegramBots.idHelper.deleteWebhookLookup')}
                                    >
                                        <AppIcon name="refresh" />
                                        <span>
                                            {t('adminTelegramBots.idHelper.deleteWebhookLookup')}
                                        </span>
                                    </button>
                                ) : null}
                            </div>
                        ) : null}
                        {hasLookupResults ? (
                            <div className="admin-telegram-bots__id-results">
                                {idLookupState.chats.length > 0 ? (
                                    <section>
                                        <h5>{t('adminTelegramBots.idHelper.chatResults')}</h5>
                                        <div>
                                            {idLookupState.chats.map((chat) => {
                                                const id = normalizeCandidateId(chat.id);
                                                const isAdded = form.allowed_chat_ids.includes(id);

                                                return (
                                                    <button
                                                        type="button"
                                                        className={`admin-telegram-bots__id-result ${
                                                            isAdded ? 'is-added' : ''
                                                        }`}
                                                        key={`chat-${id}`}
                                                        data-id={id}
                                                        disabled={isAdded}
                                                        onClick={handleAddLookupChatId}
                                                        title={t(
                                                            isAdded
                                                                ? 'adminTelegramBots.idHelper.added'
                                                                : 'adminTelegramBots.idHelper.addChat',
                                                        )}
                                                    >
                                                        <span>
                                                            <strong>{chat.label || id}</strong>
                                                            <small>
                                                                {chat.type ? `${chat.type} - ` : ''}
                                                                {id}
                                                            </small>
                                                        </span>
                                                        <AppIcon
                                                            name={isAdded ? 'check' : 'plus'}
                                                        />
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </section>
                                ) : null}
                                {idLookupState.users.length > 0 ? (
                                    <section>
                                        <h5>{t('adminTelegramBots.idHelper.userResults')}</h5>
                                        <div>
                                            {idLookupState.users.map((user) => {
                                                const id = normalizeCandidateId(user.id);
                                                const isAdded = form.allowed_user_ids.includes(id);

                                                return (
                                                    <button
                                                        type="button"
                                                        className={`admin-telegram-bots__id-result ${
                                                            isAdded ? 'is-added' : ''
                                                        }`}
                                                        key={`user-${id}`}
                                                        data-id={id}
                                                        disabled={isAdded}
                                                        onClick={handleAddLookupUserId}
                                                        title={t(
                                                            isAdded
                                                                ? 'adminTelegramBots.idHelper.added'
                                                                : 'adminTelegramBots.idHelper.addUser',
                                                        )}
                                                    >
                                                        <span>
                                                            <strong>{user.label || id}</strong>
                                                            <small>{id}</small>
                                                        </span>
                                                        <AppIcon
                                                            name={isAdded ? 'check' : 'plus'}
                                                        />
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </section>
                                ) : null}
                            </div>
                        ) : null}
                    </section>

                    <section className="admin-telegram-bots__create-form-panel">
                        <header className="admin-telegram-bots__create-section-head">
                            <h4>{t('adminTelegramBots.fields.allowedActions')}</h4>
                            <p>{t('adminTelegramBots.sections.allowedActionsText')}</p>
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

                    <section className="admin-telegram-bots__create-form-panel">
                        <header className="admin-telegram-bots__create-section-head">
                            <h4>{t('adminTelegramBots.fields.allowedUpdates')}</h4>
                            <p>{t('adminTelegramBots.sections.allowedUpdatesText')}</p>
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

                    <TelegramBotCreateStatus t={t} form={form} />

                    <div className="admin-telegram-bots__drawer-actions">
                        <button
                            type="button"
                            className="app-button app-button--ghost"
                            disabled={creating}
                            onClick={requestClose}
                            title={t('common.cancel')}
                        >
                            <AppIcon name="x" />
                            {t('common.cancel')}
                        </button>
                        <button
                            type="submit"
                            className="app-button app-button--primary"
                            title={submitTitle}
                            aria-describedby="telegram-bot-create-submit-help"
                            disabled={submitDisabled}
                        >
                            <AppIcon name="plus" />
                            {creating
                                ? t('adminTelegramBots.creating')
                                : t('adminTelegramBots.createButton')}
                        </button>
                        {submitDisabled ? (
                            <span
                                id="telegram-bot-create-submit-help"
                                className="admin-telegram-bots__submit-help"
                            >
                                <AppIcon name="info" />
                                {submitTitle}
                            </span>
                        ) : (
                            <span
                                id="telegram-bot-create-submit-help"
                                className="admin-telegram-bots__sr-only"
                            >
                                {submitTitle}
                            </span>
                        )}
                    </div>
                </form>
            </aside>
        </div>,
        document.body,
    );
}

export default memo(TelegramBotCreateDrawer);
