import { lazy, Suspense, useCallback, useDeferredValue, useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import AppIcon from '../../../../components/icons/AppIcon';
import ConfirmModal from '../../../../components/ui/ConfirmModal';
import ErrorState from '../../../../components/ui/ErrorState';
import LoadingState from '../../../../components/ui/LoadingState';
import api from '../../../../lib/api';
import { useLanguage } from '../../../i18n/context/LanguageContext';
import TelegramBotListPanel from './telegram-bots/TelegramBotListPanel';
import TelegramBotSummaryCards from './telegram-bots/TelegramBotSummaryCards';
import '../../../../../../scss/modules/_admin-telegram-bots.scss';
import {
    buildBotForm,
    buildBotPayload,
    buildBotSearchIndex,
    buildCreateForm,
    buildWebhookForm,
    filterBots,
    firstErrorMessage,
    hasFormChanged,
} from './telegram-bots/telegramBotAdmin.helpers';

const TelegramBotCreateDrawer = lazy(() => import('./telegram-bots/TelegramBotCreateDrawer'));
const TelegramBotEditModal = lazy(() => import('./telegram-bots/TelegramBotEditModal'));
const TelegramBotSecretRevealModal = lazy(
    () => import('./telegram-bots/TelegramBotSecretRevealModal'),
);

/**
 * Render the Telegram bot admin screen that manages DB-backed bot configuration.
 *
 * @returns {import('react').JSX.Element}
 */
export default function AdminTelegramBotsPage() {
    const { key = '' } = useParams();
    const navigate = useNavigate();
    const { language, t } = useLanguage();
    const [bots, setBots] = useState([]);
    const [forms, setForms] = useState({});
    const [initialForms, setInitialForms] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [flash, setFlash] = useState('');
    const [savingKey, setSavingKey] = useState('');
    const [deletingKey, setDeletingKey] = useState('');
    const [confirmDeleteBot, setConfirmDeleteBot] = useState(null);
    const [confirmToggleBot, setConfirmToggleBot] = useState(null);
    const [creating, setCreating] = useState(false);
    const [runtimeInfo, setRuntimeInfo] = useState(null);
    const [webhookInfo, setWebhookInfo] = useState(null);
    const [webhookForm, setWebhookForm] = useState(buildWebhookForm(null));
    const [auditEntries, setAuditEntries] = useState([]);
    const [auditLoadedKey, setAuditLoadedKey] = useState('');
    const [auditLoading, setAuditLoading] = useState(false);
    const [revealedSecrets, setRevealedSecrets] = useState({});
    const [secretPrompt, setSecretPrompt] = useState({
        secretKey: '',
        password: '',
        loading: false,
        error: '',
    });
    const [operationsLoading, setOperationsLoading] = useState(false);
    const [createForm, setCreateForm] = useState(() => buildCreateForm(language));
    const [createDrawerOpen, setCreateDrawerOpen] = useState(false);
    const [editingKey, setEditingKey] = useState(key);
    const [filters, setFilters] = useState({
        search: '',
        environment: '',
        purpose: '',
        status: '',
    });
    const deferredFilters = useDeferredValue(filters);
    const [listPage, setListPage] = useState(1);

    const loadBots = useCallback(async () => {
        setLoading(true);

        try {
            const response = await api.get('/admin/auto-coding/telegram-bots');
            const nextBots = (response.data.data ?? []).map((bot) => ({
                ...bot,
                search_index: buildBotSearchIndex(bot),
            }));
            const nextForms = Object.fromEntries(
                nextBots.map((bot) => [bot.key, buildBotForm(bot)]),
            );

            setBots(nextBots);
            setForms(nextForms);
            setInitialForms(nextForms);
            setError('');
        } catch (requestError) {
            setBots([]);
            setError(requestError?.response?.data?.message || t('adminTelegramBots.loadError'));
        } finally {
            setLoading(false);
        }
    }, [t]);

    const loadRuntimeInfo = useCallback(async () => {
        setOperationsLoading(true);

        try {
            const [runtimeResponse, webhookResponse] = await Promise.all([
                api.get('/admin/auto-coding/telegram-bots/runtime'),
                api.get('/admin/auto-coding/telegram-bots/webhook'),
            ]);

            setRuntimeInfo(runtimeResponse.data.data ?? null);
            const nextWebhookInfo = webhookResponse.data.data ?? null;

            setWebhookInfo(nextWebhookInfo);
            setWebhookForm((current) => ({
                ...current,
                url: nextWebhookInfo?.result?.url ?? '',
            }));
        } catch (requestError) {
            setError(
                requestError?.response?.data?.message || t('adminTelegramBots.runtimeLoadError'),
            );
        } finally {
            setOperationsLoading(false);
        }
    }, [t]);

    const loadAuditEntries = useCallback(
        async (botKey) => {
            if (!botKey) {
                setAuditEntries([]);

                return;
            }

            setAuditLoading(true);

            try {
                const response = await api.get(`/admin/auto-coding/telegram-bots/${botKey}/audits`);

                setAuditEntries(response.data.data ?? []);
                setAuditLoadedKey(botKey);
            } catch (requestError) {
                setError(
                    requestError?.response?.data?.message || t('adminTelegramBots.auditLoadError'),
                );
            } finally {
                setAuditLoading(false);
            }
        },
        [t],
    );

    useEffect(() => {
        void loadBots();
    }, [loadBots]);

    const filteredBots = useMemo(() => filterBots(bots, deferredFilters), [bots, deferredFilters]);

    const selectedBot = useMemo(
        () => bots.find((bot) => bot.key === editingKey) ?? null,
        [bots, editingKey],
    );
    const selectedBotKey = selectedBot?.key ?? '';
    const selectedForm = selectedBotKey ? forms[selectedBotKey] : null;
    const initialSelectedForm = selectedBotKey ? initialForms[selectedBotKey] : null;
    const hasChanges = useMemo(
        () => hasFormChanged(selectedForm, initialSelectedForm),
        [initialSelectedForm, selectedForm],
    );

    useEffect(() => {
        setEditingKey(key);
    }, [key]);

    useEffect(() => {
        setListPage(1);
    }, [filters]);

    useEffect(() => {
        if (selectedBot?.key) {
            void loadRuntimeInfo();
            setRevealedSecrets({});
            setSecretPrompt({
                secretKey: '',
                password: '',
                loading: false,
                error: '',
            });
            setAuditEntries([]);
            setAuditLoadedKey('');
        }
    }, [loadRuntimeInfo, selectedBot?.key]);

    const handleAuditTabOpen = useCallback(() => {
        if (selectedBot?.key && auditLoadedKey !== selectedBot.key) {
            void loadAuditEntries(selectedBot.key);
        }
    }, [auditLoadedKey, loadAuditEntries, selectedBot?.key]);

    const handleFilterChange = useCallback((field, value) => {
        setFilters((current) => {
            if (current[field] === value) {
                return current;
            }

            return { ...current, [field]: value };
        });
    }, []);

    const handleWebhookFormChange = useCallback((field, value) => {
        setWebhookForm((current) => {
            if (current[field] === value) {
                return current;
            }

            return { ...current, [field]: value };
        });
    }, []);

    const handleCreateFormChange = useCallback((field, value) => {
        setCreateForm((current) => {
            if (current[field] === value) {
                return current;
            }

            return { ...current, [field]: value };
        });
    }, []);

    const handleOpenCreateDrawer = useCallback(() => {
        setCreateDrawerOpen(true);
    }, []);

    const handleCloseCreateDrawer = useCallback(() => {
        setCreateDrawerOpen(false);
    }, []);

    const handleEditBot = useCallback(
        (botKey) => {
            setEditingKey(botKey);
            navigate(`/admin/auto-coding/telegram-bots/${botKey}`);
        },
        [navigate],
    );

    const handleCloseEditModal = useCallback(() => {
        setEditingKey('');
        navigate('/admin/auto-coding/telegram-bots');
    }, [navigate]);

    const handleDeleteBotRequest = useCallback(() => {
        if (!selectedBot) {
            return;
        }

        setConfirmDeleteBot(selectedBot);
    }, [selectedBot]);

    const handleDeleteBotFromTable = useCallback((bot) => {
        setConfirmDeleteBot(bot);
    }, []);

    const handleCancelDeleteBot = useCallback(() => {
        if (deletingKey === '') {
            setConfirmDeleteBot(null);
        }
    }, [deletingKey]);

    const handleCancelToggleBot = useCallback(() => {
        if (savingKey === '') {
            setConfirmToggleBot(null);
        }
    }, [savingKey]);

    const handleConfirmDeleteBot = useCallback(async () => {
        if (!confirmDeleteBot) {
            return;
        }

        setDeletingKey(confirmDeleteBot.key);
        setFlash('');
        setError('');

        try {
            await api.delete(`/admin/auto-coding/telegram-bots/${confirmDeleteBot.key}`);

            setFlash(`${t('adminTelegramBots.deletedPrefix')} ${confirmDeleteBot.display_name}.`);
            setConfirmDeleteBot(null);
            setEditingKey('');
            setAuditEntries([]);
            setAuditLoadedKey('');
            setRevealedSecrets({});
            navigate('/admin/auto-coding/telegram-bots');
            await loadBots();
            await loadRuntimeInfo();
        } catch (requestError) {
            setConfirmDeleteBot(null);
            setError(firstErrorMessage(requestError, t('adminTelegramBots.deleteError')));
        } finally {
            setDeletingKey('');
        }
    }, [confirmDeleteBot, loadBots, loadRuntimeInfo, navigate, t]);

    const handleDismissFlash = useCallback(() => {
        setFlash('');
    }, []);

    const handleDismissError = useCallback(() => {
        setError('');
    }, []);

    const handleRefreshAll = useCallback(() => {
        void loadBots();
        if (editingKey !== '') {
            void loadRuntimeInfo();
        }
    }, [editingKey, loadBots, loadRuntimeInfo]);

    const handleRevealSecretRequest = useCallback((secretKey) => {
        setSecretPrompt({
            secretKey,
            password: '',
            loading: false,
            error: '',
        });
    }, []);

    const handleSecretPasswordChange = useCallback((value) => {
        setSecretPrompt((current) => ({
            ...current,
            password: value,
            error: '',
        }));
    }, []);

    const handleCloseSecretPrompt = useCallback(() => {
        setSecretPrompt({
            secretKey: '',
            password: '',
            loading: false,
            error: '',
        });
    }, []);

    const updateSelectedForm = useCallback(
        (field, value) => {
            if (!selectedBotKey) {
                return;
            }

            setForms((current) => ({
                ...current,
                [selectedBotKey]: {
                    ...current[selectedBotKey],
                    [field]: value,
                },
            }));
            setFlash('');
            setError('');
        },
        [selectedBotKey],
    );

    const handleSave = useCallback(async () => {
        if (!selectedBot || !selectedForm || !hasChanges) {
            return;
        }

        setSavingKey(selectedBot.key);
        setFlash('');
        setError('');

        try {
            const response = await api.put(
                `/admin/auto-coding/telegram-bots/${selectedBot.key}`,
                buildBotPayload(selectedForm),
            );
            const savedBot = response.data.data;

            setFlash(`${t('adminTelegramBots.savedPrefix')} ${savedBot.display_name}.`);
            await loadBots();
            await loadRuntimeInfo();
            if (auditLoadedKey === selectedBot.key) {
                await loadAuditEntries(selectedBot.key);
            }
        } catch (requestError) {
            setError(firstErrorMessage(requestError, t('adminTelegramBots.saveError')));
        } finally {
            setSavingKey('');
        }
    }, [
        auditLoadedKey,
        hasChanges,
        loadAuditEntries,
        loadBots,
        loadRuntimeInfo,
        selectedBot,
        selectedForm,
        t,
    ]);

    const handleSaveRequest = useCallback(() => {
        void handleSave();
    }, [handleSave]);

    const handleToggleBot = useCallback(
        async (bot) => {
            const form = forms[bot.key] ?? buildBotForm(bot);

            if (!form) {
                return;
            }

            setSavingKey(bot.key);
            setFlash('');
            setError('');

            try {
                const nextEnabled = !bot.enabled;
                const response = await api.put(
                    `/admin/auto-coding/telegram-bots/${bot.key}`,
                    buildBotPayload({
                        ...form,
                        enabled: nextEnabled,
                        is_default: nextEnabled ? true : form.is_default,
                    }),
                );
                const savedBot = response.data.data;

                setFlash(
                    `${
                        nextEnabled
                            ? t('adminTelegramBots.activatedPrefix')
                            : t('adminTelegramBots.deactivatedPrefix')
                    } ${savedBot.display_name}.`,
                );
                await loadBots();
                await loadRuntimeInfo();
            } catch (requestError) {
                setError(firstErrorMessage(requestError, t('adminTelegramBots.toggleError')));
            } finally {
                setSavingKey('');
            }
        },
        [forms, loadBots, loadRuntimeInfo, t],
    );

    const handleActivateBotRequest = useCallback((bot) => {
        setConfirmToggleBot(bot);
    }, []);

    const handleConfirmToggleBot = useCallback(() => {
        if (!confirmToggleBot) {
            return;
        }

        void handleToggleBot(confirmToggleBot);
        setConfirmToggleBot(null);
    }, [confirmToggleBot, handleToggleBot]);

    const handleCreate = useCallback(
        async (event) => {
            event.preventDefault();

            if (createForm.key.trim() === '' || createForm.display_name.trim() === '') {
                return;
            }

            setCreating(true);
            setFlash('');
            setError('');

            try {
                const secretConfig = {};

                if (createForm.bot_token.trim() !== '') {
                    secretConfig.bot_token = createForm.bot_token.trim();
                }

                if (createForm.webhook_secret.trim() !== '') {
                    secretConfig.webhook_secret = createForm.webhook_secret.trim();
                }

                const response = await api.post('/admin/auto-coding/telegram-bots', {
                    key: createForm.key.trim(),
                    display_name: createForm.display_name.trim(),
                    purpose: createForm.purpose,
                    environment: createForm.environment,
                    machine_group: createForm.machine_group.trim() || null,
                    locale: createForm.locale,
                    enabled: createForm.enabled,
                    is_default: createForm.is_default || bots.length === 0,
                    allowed_actions: createForm.allowed_actions,
                    allowed_chat_ids: createForm.allowed_chat_ids,
                    allowed_user_ids: createForm.allowed_user_ids,
                    public_config: {
                        allowed_updates: createForm.allowed_updates,
                        description: createForm.description.trim() || null,
                        chat_history_limit: 30,
                        chat_session_timeline_limit: 6,
                    },
                    ...(Object.keys(secretConfig).length > 0
                        ? {
                              secret_config: secretConfig,
                          }
                        : {}),
                });
                const createdBot = response.data.data;

                setCreateForm(buildCreateForm(language));
                setCreateDrawerOpen(false);
                setFlash(`${t('adminTelegramBots.createdPrefix')} ${createdBot.display_name}.`);
                await loadBots();
                setEditingKey(createdBot.key);
                navigate(`/admin/auto-coding/telegram-bots/${createdBot.key}`);
            } catch (requestError) {
                setError(firstErrorMessage(requestError, t('adminTelegramBots.createError')));
            } finally {
                setCreating(false);
            }
        },
        [bots.length, createForm, language, loadBots, navigate, t],
    );

    const handleSyncCommands = useCallback(async () => {
        setOperationsLoading(true);
        setFlash('');
        setError('');

        try {
            const response = await api.post('/admin/auto-coding/telegram-bots/commands-sync');
            const payload = response.data.data ?? {};

            setFlash(
                payload.ok === true
                    ? t('adminTelegramBots.syncSuccess')
                    : payload.description || t('adminTelegramBots.syncError'),
            );
            await loadRuntimeInfo();
            if (selectedBot?.key && auditLoadedKey === selectedBot.key) {
                await loadAuditEntries(selectedBot.key);
            }
        } catch (requestError) {
            setError(firstErrorMessage(requestError, t('adminTelegramBots.syncError')));
        } finally {
            setOperationsLoading(false);
        }
    }, [auditLoadedKey, loadAuditEntries, loadRuntimeInfo, selectedBot?.key, t]);

    const handleSyncCommandsRequest = useCallback(() => {
        void handleSyncCommands();
    }, [handleSyncCommands]);

    const handleRefreshRuntimeRequest = useCallback(() => {
        void loadRuntimeInfo();
    }, [loadRuntimeInfo]);

    const handleWebhookOperation = useCallback(
        async (operation) => {
            if (operation === 'register' && webhookForm.url.trim() === '') {
                return;
            }

            setOperationsLoading(true);
            setFlash('');
            setError('');

            const endpoint =
                operation === 'register'
                    ? '/admin/auto-coding/telegram-bots/webhook/register'
                    : '/admin/auto-coding/telegram-bots/webhook/delete';

            const payload = {
                drop_pending_updates: webhookForm.drop_pending_updates,
            };

            if (operation === 'register') {
                payload.url = webhookForm.url.trim();
            }

            try {
                const response = await api.post(endpoint, payload);
                const result = response.data.data ?? {};

                setFlash(
                    result.ok === true
                        ? operation === 'register'
                            ? t('adminTelegramBots.webhookRegisterSuccess')
                            : t('adminTelegramBots.webhookDeleteSuccess')
                        : result.description ||
                              (operation === 'register'
                                  ? t('adminTelegramBots.webhookRegisterError')
                                  : t('adminTelegramBots.webhookDeleteError')),
                );
                await loadRuntimeInfo();
                if (selectedBot?.key && auditLoadedKey === selectedBot.key) {
                    await loadAuditEntries(selectedBot.key);
                }
            } catch (requestError) {
                setError(
                    firstErrorMessage(
                        requestError,
                        operation === 'register'
                            ? t('adminTelegramBots.webhookRegisterError')
                            : t('adminTelegramBots.webhookDeleteError'),
                    ),
                );
            } finally {
                setOperationsLoading(false);
            }
        },
        [auditLoadedKey, loadAuditEntries, loadRuntimeInfo, selectedBot?.key, t, webhookForm],
    );

    const handleRegisterWebhookRequest = useCallback(() => {
        void handleWebhookOperation('register');
    }, [handleWebhookOperation]);

    const handleDeleteWebhookRequest = useCallback(() => {
        if (
            typeof window !== 'undefined' &&
            !window.confirm(t('adminTelegramBots.webhookDeleteConfirm'))
        ) {
            return;
        }

        void handleWebhookOperation('delete');
    }, [handleWebhookOperation, t]);

    const handleSecretReveal = useCallback(async () => {
        if (!selectedBot || secretPrompt.secretKey === '' || secretPrompt.password.trim() === '') {
            return;
        }

        setSecretPrompt((current) => ({
            ...current,
            loading: true,
            error: '',
        }));

        try {
            const response = await api.post(
                `/admin/auto-coding/telegram-bots/${selectedBot.key}/reveal-secret`,
                {
                    secret_key: secretPrompt.secretKey,
                    password: secretPrompt.password,
                },
            );
            const payload = response.data.data ?? {};

            setRevealedSecrets((current) => ({
                ...current,
                [payload.secret_key]: payload.value ?? '',
            }));
            setSecretPrompt({
                secretKey: '',
                password: '',
                loading: false,
                error: '',
            });
            if (auditLoadedKey === selectedBot.key) {
                await loadAuditEntries(selectedBot.key);
            }
        } catch (requestError) {
            setSecretPrompt((current) => ({
                ...current,
                loading: false,
                error: firstErrorMessage(requestError, t('adminTelegramBots.secretRevealError')),
            }));
        }
    }, [auditLoadedKey, loadAuditEntries, secretPrompt, selectedBot, t]);

    const handleSecretRevealConfirm = useCallback(() => {
        void handleSecretReveal();
    }, [handleSecretReveal]);

    if (loading) {
        return <LoadingState text={t('adminTelegramBots.loading')} />;
    }

    if (error && bots.length === 0) {
        return <ErrorState text={error} />;
    }

    return (
        <div className="admin-telegram-bots admin-telegram-bots--dashboard">
            <header className="admin-telegram-bots__page-head">
                <div className="admin-telegram-bots__page-copy">
                    <h1>{t('adminTelegramBots.pageTitle')}</h1>
                    <p>{t('adminTelegramBots.pageSubtitle')}</p>
                </div>
                <div className="admin-telegram-bots__page-actions">
                    <button
                        type="button"
                        className="app-button app-button--primary"
                        onClick={handleOpenCreateDrawer}
                    >
                        <AppIcon name="plus" />
                        {t('adminTelegramBots.addButton')}
                    </button>
                </div>
            </header>

            {flash ? (
                <div className="app-alert app-alert--success" role="status">
                    <AppIcon name="check" />
                    <span>{flash}</span>
                    <button
                        type="button"
                        onClick={handleDismissFlash}
                        aria-label={t('adminTelegramBots.dismissMessage')}
                        title={t('adminTelegramBots.dismissMessage')}
                    >
                        <AppIcon name="x" />
                    </button>
                </div>
            ) : null}
            {error ? (
                <div className="app-alert app-alert--danger" role="alert">
                    <AppIcon name="info" />
                    <span>{error}</span>
                    <button
                        type="button"
                        onClick={handleDismissError}
                        aria-label={t('adminTelegramBots.dismissMessage')}
                        title={t('adminTelegramBots.dismissMessage')}
                    >
                        <AppIcon name="x" />
                    </button>
                </div>
            ) : null}

            <TelegramBotSummaryCards bots={bots} t={t} />

            <section className="admin-telegram-bots__layout">
                <TelegramBotListPanel
                    t={t}
                    bots={filteredBots}
                    totalBotCount={bots.length}
                    filters={filters}
                    page={listPage}
                    onPageChange={setListPage}
                    onFilterChange={handleFilterChange}
                    onRefresh={handleRefreshAll}
                    onEdit={handleEditBot}
                    onActivate={handleActivateBotRequest}
                    onDelete={handleDeleteBotFromTable}
                    savingKey={savingKey}
                />
            </section>

            {selectedBot && selectedForm ? (
                <Suspense fallback={null}>
                    <TelegramBotEditModal
                        open
                        t={t}
                        bot={selectedBot}
                        form={selectedForm}
                        saving={savingKey === selectedBot.key}
                        hasChanges={hasChanges}
                        operationsLoading={operationsLoading}
                        runtimeInfo={runtimeInfo}
                        webhookInfo={webhookInfo}
                        webhookForm={webhookForm}
                        auditEntries={auditEntries}
                        auditLoading={auditLoading}
                        revealedSecrets={revealedSecrets}
                        onClose={handleCloseEditModal}
                        onChange={updateSelectedForm}
                        onSave={handleSaveRequest}
                        onWebhookFormChange={handleWebhookFormChange}
                        onRefreshRuntime={handleRefreshRuntimeRequest}
                        onRegisterWebhook={handleRegisterWebhookRequest}
                        onDeleteWebhook={handleDeleteWebhookRequest}
                        onSyncCommands={handleSyncCommandsRequest}
                        onDeleteBot={handleDeleteBotRequest}
                        onAuditTabOpen={handleAuditTabOpen}
                        onRevealSecret={handleRevealSecretRequest}
                    />
                </Suspense>
            ) : null}

            {createDrawerOpen ? (
                <Suspense fallback={null}>
                    <TelegramBotCreateDrawer
                        open={createDrawerOpen}
                        t={t}
                        form={createForm}
                        initialLocale={language === 'vi' ? 'vi' : 'en'}
                        creating={creating}
                        onClose={handleCloseCreateDrawer}
                        onChange={handleCreateFormChange}
                        onSubmit={handleCreate}
                    />
                </Suspense>
            ) : null}

            {secretPrompt.secretKey !== '' ? (
                <Suspense fallback={null}>
                    <TelegramBotSecretRevealModal
                        open={secretPrompt.secretKey !== ''}
                        t={t}
                        loading={secretPrompt.loading}
                        password={secretPrompt.password}
                        error={secretPrompt.error}
                        onPasswordChange={handleSecretPasswordChange}
                        onClose={handleCloseSecretPrompt}
                        onConfirm={handleSecretRevealConfirm}
                    />
                </Suspense>
            ) : null}

            <ConfirmModal
                open={Boolean(confirmToggleBot)}
                eyebrow={
                    confirmToggleBot?.enabled
                        ? t('adminTelegramBots.toggleModal.disableEyebrow')
                        : t('adminTelegramBots.toggleModal.enableEyebrow')
                }
                title={
                    confirmToggleBot?.enabled
                        ? t('adminTelegramBots.toggleModal.disableTitle')
                        : t('adminTelegramBots.toggleModal.enableTitle')
                }
                text={`${
                    confirmToggleBot?.enabled
                        ? t('adminTelegramBots.toggleModal.disableText')
                        : t('adminTelegramBots.toggleModal.enableText')
                } ${confirmToggleBot?.display_name ?? ''}`}
                confirmLabel={
                    confirmToggleBot?.enabled
                        ? t('adminTelegramBots.toggleModal.disableConfirm')
                        : t('adminTelegramBots.toggleModal.enableConfirm')
                }
                cancelLabel={t('common.cancel')}
                tone={confirmToggleBot?.enabled ? 'danger' : 'primary'}
                confirmDisabled={savingKey !== ''}
                cancelDisabled={savingKey !== ''}
                onConfirm={handleConfirmToggleBot}
                onCancel={handleCancelToggleBot}
            />

            <ConfirmModal
                open={Boolean(confirmDeleteBot)}
                eyebrow={t('adminTelegramBots.deleteModal.eyebrow')}
                title={t('adminTelegramBots.deleteModal.title')}
                text={`${t('adminTelegramBots.deleteModal.text')} ${confirmDeleteBot?.display_name ?? ''}`}
                confirmLabel={t('adminTelegramBots.deleteModal.confirm')}
                cancelLabel={t('common.cancel')}
                tone="danger"
                confirmDisabled={deletingKey !== ''}
                cancelDisabled={deletingKey !== ''}
                onConfirm={handleConfirmDeleteBot}
                onCancel={handleCancelDeleteBot}
            />
        </div>
    );
}
