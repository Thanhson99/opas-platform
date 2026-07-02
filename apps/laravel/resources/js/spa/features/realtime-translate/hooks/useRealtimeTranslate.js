import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    createRealtimeServiceClient,
    delay,
    errorMessage,
    openExtensionSetupScreen,
    requestExtension,
    realtimeTranslateUrl,
} from '../services/realtimeTranslate.service';

const MAX_LINES = 14;
const ACCEPTED_LANGUAGES = ['vi', 'en'];
const DEFAULT_STT_PROFILE = 'balanced';

/**
 * Own realtime translate state for the Laravel workspace page.
 *
 * @returns {Record<string, unknown>}
 */
export default function useRealtimeTranslate() {
    const client = useMemo(() => createRealtimeServiceClient(), []);
    const transcriptSocketRef = useRef(null);
    const transcriptTimerRef = useRef(null);
    const statusTimerRef = useRef(null);
    const [serviceStatus, setServiceStatus] = useState('checking');
    const [extensionStatus, setExtensionStatus] = useState('checking');
    const [phase, setPhase] = useState('idle');
    const [tabs, setTabs] = useState([]);
    const [selectedSourceId, setSelectedSourceId] = useState('');
    const [sessionId, setSessionId] = useState('');
    const [lines, setLines] = useState([]);
    const [notes, setNotes] = useState('');
    const [metrics, setMetrics] = useState({ chunks: 0, bytes: 0, stt: 'idle' });
    const [sttConfig, setSttConfig] = useState(null);
    const [prompt, setPrompt] = useState('');
    const [profile, setProfile] = useState(DEFAULT_STT_PROFILE);
    const [languageFilter, setLanguageFilter] = useState(ACCEPTED_LANGUAGES);
    const [error, setError] = useState('');

    const selectedTab = useMemo(
        () => tabs.find((tab) => tab.source_id === selectedSourceId) || null,
        [selectedSourceId, tabs],
    );

    const loadService = useCallback(async () => {
        const [healthPayload, configPayload] = await Promise.all([
            client.health(),
            client.sttConfig(),
        ]);

        setServiceStatus(String(healthPayload.stt_status || 'running'));
        setSttConfig(configPayload);
        setProfile(String(configPayload.profile || DEFAULT_STT_PROFILE));
        setPrompt(String(configPayload.prompt || ''));
        setLanguageFilter(Array.isArray(configPayload.languages) ? configPayload.languages : []);

        return configPayload;
    }, [client]);

    const warmStt = useCallback(async () => {
        await client.warmup();
        const configPayload = await client.sttConfig();
        setSttConfig(configPayload);
        return configPayload;
    }, [client]);

    const loadTabs = useCallback(async () => {
        const status = await requestExtension('GET_STATUS', {}, 900);
        const response = await requestExtension('LIST_TABS');
        const nextTabs = Array.isArray(response.tabs) ? response.tabs : [];
        const activeTab = nextTabs.find((tab) => tab.active) || nextTabs[0] || null;

        setExtensionStatus('connected');
        setTabs(nextTabs);
        setSelectedSourceId((currentSourceId) => {
            if (currentSourceId && nextTabs.some((tab) => tab.source_id === currentSourceId)) {
                return currentSourceId;
            }

            return activeTab?.source_id || '';
        });

        if (status.activeCapture?.sessionId) {
            setSessionId(String(status.activeCapture.sessionId));
        }
    }, []);

    const refresh = useCallback(async () => {
        setError('');
        setPhase((currentPhase) => (currentPhase === 'capturing' ? currentPhase : 'checking'));

        try {
            await Promise.all([loadService(), loadTabs()]);
            await warmStt();
            setPhase((currentPhase) => (currentPhase === 'capturing' ? currentPhase : 'ready'));
        } catch (caughtError) {
            setError(errorMessage(caughtError));
            setPhase('setup');
        }
    }, [loadService, loadTabs, warmStt]);

    const setup = useCallback(async () => {
        setError('');
        setPhase('setup');
        openExtensionSetupScreen();

        try {
            await requestExtension('INJECT_WEB_BRIDGE');
            await refresh();
        } catch (caughtError) {
            setError(errorMessage(caughtError));
        }
    }, [refresh]);

    const saveConfig = useCallback(async () => {
        const configPayload = await client.configureStt({
            profile,
            languages: languageFilter,
            prompt,
        });

        setSttConfig(configPayload);
        if (!configPayload.model_loaded) {
            await warmStt();
        }
    }, [client, languageFilter, profile, prompt, warmStt]);

    const ensureSttReady = useCallback(async () => {
        let configPayload = sttConfig?.model_loaded ? sttConfig : await warmStt();
        const startedAt = Date.now();

        while (!configPayload.model_loaded && Date.now() - startedAt < 120000) {
            setPhase('preparing');
            await delay(1000);
            configPayload = await client.sttConfig();
            setSttConfig(configPayload);
        }

        if (!configPayload.model_loaded) {
            throw new Error('STT model did not finish loading in time.');
        }
    }, [client, sttConfig, warmStt]);

    const applyTranscriptPayload = useCallback((payload) => {
        setMetrics((currentMetrics) => ({
            ...currentMetrics,
            stt: String(payload.status || 'waiting'),
        }));
        setNotes((payload.notes || []).slice(0, 4).join('\n'));
        setLines((currentLines) =>
            dedupeLines([
                ...currentLines,
                ...(payload.lines || []).map((line) => ({
                    key: `${line.started_at || ''}:${line.original_text || ''}`,
                    speaker: line.speaker || 'Speaker',
                    text: line.original_text || '',
                    detectedLanguage: line.detected_language || '',
                    confidence: line.confidence,
                    translatedText: line.translated_text || '',
                })),
            ]).slice(-MAX_LINES),
        );
    }, []);

    const stopTranscriptWatch = useCallback(() => {
        transcriptSocketRef.current?.close();
        transcriptSocketRef.current = null;

        if (transcriptTimerRef.current) {
            window.clearInterval(transcriptTimerRef.current);
            transcriptTimerRef.current = null;
        }
    }, []);

    const stopStatusWatch = useCallback(() => {
        if (statusTimerRef.current) {
            window.clearInterval(statusTimerRef.current);
            statusTimerRef.current = null;
        }
    }, []);

    const startTranscriptWatch = useCallback(
        (nextSessionId) => {
            stopTranscriptWatch();
            const socket = new WebSocket(client.websocketUrl(nextSessionId));
            transcriptSocketRef.current = socket;
            socket.onmessage = (event) => applyTranscriptPayload(JSON.parse(event.data));
            socket.onclose = () => {
                transcriptSocketRef.current = null;
            };

            transcriptTimerRef.current = window.setInterval(async () => {
                const payload = await client.transcript(nextSessionId).catch(() => null);
                if (payload) {
                    applyTranscriptPayload(payload);
                }
            }, 900);
        },
        [applyTranscriptPayload, client, stopTranscriptWatch],
    );

    const startStatusWatch = useCallback(() => {
        stopStatusWatch();
        statusTimerRef.current = window.setInterval(async () => {
            const response = await requestExtension('GET_STATUS').catch(() => null);
            const capture = response?.activeCapture;

            if (!capture) {
                return;
            }

            setMetrics((currentMetrics) => ({
                ...currentMetrics,
                chunks: Number(capture.chunksSent || 0),
                bytes: Number(capture.bytesSent || 0),
            }));
        }, 700);
    }, [stopStatusWatch]);

    const connect = useCallback(async () => {
        if (!selectedTab) {
            setError('Select a browser tab first.');
            return;
        }

        setError('');
        setLines([]);
        setNotes('');
        setPhase('preparing');

        try {
            await saveConfig();
            await ensureSttReady();
            setPhase('connecting');
            const response = await requestExtension('START_CAPTURE', {
                backendUrl: client.backendUrl,
                sourceId: selectedTab.source_id,
                webUrl: realtimeTranslateUrl(),
            });
            const nextSessionId = String(response.session?.session_id || '');
            setSessionId(nextSessionId);
            setPhase('capturing');
            startTranscriptWatch(nextSessionId);
            startStatusWatch();
        } catch (caughtError) {
            setError(errorMessage(caughtError));
            setPhase('ready');
        }
    }, [
        client.backendUrl,
        ensureSttReady,
        saveConfig,
        selectedTab,
        startStatusWatch,
        startTranscriptWatch,
    ]);

    const stop = useCallback(async () => {
        await requestExtension('STOP_CAPTURE').catch(() => {});
        stopTranscriptWatch();
        stopStatusWatch();
        setSessionId('');
        setPhase('ready');
        setMetrics({ chunks: 0, bytes: 0, stt: 'idle' });
    }, [stopStatusWatch, stopTranscriptWatch]);

    const focusSelectedTab = useCallback(async () => {
        if (!selectedTab) {
            return;
        }

        await requestExtension('FOCUS_TAB', { tabId: selectedTab.extension_tab_id }).catch(
            (caughtError) => {
                setError(errorMessage(caughtError));
            },
        );
    }, [selectedTab]);

    useEffect(() => {
        refresh();

        return () => {
            stopTranscriptWatch();
            stopStatusWatch();
        };
    }, [refresh, stopStatusWatch, stopTranscriptWatch]);

    return {
        client,
        connect,
        error,
        extensionStatus,
        focusSelectedTab,
        languageFilter,
        lines,
        metrics,
        notes,
        phase,
        profile,
        prompt,
        refresh,
        selectedSourceId,
        selectedTab,
        serviceStatus,
        saveConfig,
        sessionId,
        setLanguageFilter,
        setProfile,
        setPrompt,
        setSelectedSourceId,
        setup,
        sttConfig,
        stop,
        tabs,
    };
}

/**
 * Remove duplicate transcript lines by stable line key.
 *
 * @param {Array<Record<string, unknown>>} lines
 * @returns {Array<Record<string, unknown>>}
 */
function dedupeLines(lines) {
    const keys = new Set();

    return lines.filter((line) => {
        if (!line.text || keys.has(line.key)) {
            return false;
        }

        keys.add(line.key);
        return true;
    });
}
