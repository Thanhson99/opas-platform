import Button from '../../../components/atoms/Button';
import AppIcon from '../../../components/icons/AppIcon';
import PageHero from '../../../components/ui/PageHero';
import { useLanguage } from '../../i18n/context/LanguageContext';
import useRealtimeTranslate from '../hooks/useRealtimeTranslate';

const languageOptions = [
    { value: 'vi', labelKey: 'realtimeTranslate.languages.vi' },
    { value: 'en', labelKey: 'realtimeTranslate.languages.en' },
    { value: 'ja', labelKey: 'realtimeTranslate.languages.ja' },
    { value: 'zh', labelKey: 'realtimeTranslate.languages.zh' },
];

const profileOptions = [
    { value: 'live', labelKey: 'realtimeTranslate.profiles.live' },
    { value: 'fast', labelKey: 'realtimeTranslate.profiles.fast' },
    { value: 'balanced', labelKey: 'realtimeTranslate.profiles.balanced' },
    { value: 'accurate', labelKey: 'realtimeTranslate.profiles.accurate' },
];

/**
 * Render the Laravel realtime translate workspace.
 *
 * @returns {import('react').JSX.Element}
 */
export default function RealtimeTranslatePage() {
    const { t } = useLanguage();
    const realtime = useRealtimeTranslate();
    const isBusy = ['checking', 'setup', 'preparing', 'connecting'].includes(realtime.phase);
    const isCapturing = realtime.phase === 'capturing';

    return (
        <div className="app-shell realtime-translate">
            <PageHero
                eyebrow={t('realtimeTranslate.hero.eyebrow')}
                title={t('realtimeTranslate.hero.title')}
                text={t('realtimeTranslate.hero.text')}
                actions={
                    <>
                        <Button disabled={isCapturing} loading={isBusy} onClick={realtime.connect}>
                            {isCapturing
                                ? t('realtimeTranslate.actions.connected')
                                : t('realtimeTranslate.actions.connect')}
                        </Button>
                        <Button disabled={!isCapturing} onClick={realtime.stop} variant="danger">
                            {t('realtimeTranslate.actions.stop')}
                        </Button>
                        <Button onClick={realtime.setup} variant="secondary">
                            {t('realtimeTranslate.actions.setup')}
                        </Button>
                    </>
                }
                aside={
                    <div className="app-hero-card app-hero-card--compact">
                        <p className="app-hero-card__eyebrow">
                            {t('realtimeTranslate.status.title')}
                        </p>
                        <h3 className="app-hero-card__title">{statusTitle(t, realtime.phase)}</h3>
                        <p className="app-hero-card__text">
                            {realtime.notes || realtime.error || realtime.serviceStatus}
                        </p>
                    </div>
                }
            >
                <span className="realtime-translate__chip">
                    {t('realtimeTranslate.status.service')}: {realtime.serviceStatus}
                </span>
                <span className="realtime-translate__chip">
                    {t('realtimeTranslate.status.extension')}: {realtime.extensionStatus}
                </span>
            </PageHero>

            <section className="realtime-translate__layout">
                <aside className="app-surface realtime-translate__controls">
                    <ControlPanel realtime={realtime} t={t} />
                </aside>

                <section className="app-surface realtime-translate__stream">
                    <StreamHeader realtime={realtime} t={t} />
                    <TranscriptWaterfall lines={realtime.lines} phase={realtime.phase} t={t} />
                </section>
            </section>
        </div>
    );
}

/**
 * Render capture and STT controls.
 *
 * @param {{ realtime: Record<string, unknown>, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
function ControlPanel({ realtime, t }) {
    return (
        <>
            <div className="app-surface__header">
                <div>
                    <p className="app-hero__eyebrow">
                        {t('realtimeTranslate.controls.connection')}
                    </p>
                    <h2 className="app-surface__title">{t('realtimeTranslate.controls.source')}</h2>
                </div>
                <Button onClick={realtime.refresh} size="sm" variant="ghost">
                    {t('realtimeTranslate.actions.refresh')}
                </Button>
            </div>

            <label className="realtime-translate__field">
                <span>{t('realtimeTranslate.controls.tab')}</span>
                <select
                    value={realtime.selectedSourceId}
                    onChange={(event) => realtime.setSelectedSourceId(event.target.value)}
                >
                    {realtime.tabs.length ? (
                        realtime.tabs.map((tab) => (
                            <option key={tab.source_id} value={tab.source_id}>
                                {tab.active ? '[current] ' : ''}
                                {tab.title}
                            </option>
                        ))
                    ) : (
                        <option value="">{t('realtimeTranslate.controls.noTabs')}</option>
                    )}
                </select>
            </label>

            <div className="realtime-translate__tab-meta">
                {realtime.selectedTab ? (
                    <>
                        <strong>{realtime.selectedTab.browser}</strong>
                        <span>
                            {realtime.selectedTab.audible
                                ? t('realtimeTranslate.status.audible')
                                : t('realtimeTranslate.status.quiet')}
                        </span>
                        <small>{realtime.selectedTab.url}</small>
                    </>
                ) : (
                    <span>{t('realtimeTranslate.controls.selectTab')}</span>
                )}
            </div>

            <Button onClick={realtime.focusSelectedTab} variant="secondary">
                {t('realtimeTranslate.actions.openTab')}
            </Button>

            <div className="realtime-translate__divider" />

            <label className="realtime-translate__field">
                <span>{t('realtimeTranslate.controls.profile')}</span>
                <select
                    value={realtime.profile}
                    onChange={(event) => realtime.setProfile(event.target.value)}
                >
                    {profileOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {t(option.labelKey)}
                        </option>
                    ))}
                </select>
            </label>

            <div className="realtime-translate__checks">
                {languageOptions.map((option) => (
                    <label key={option.value}>
                        <input
                            checked={realtime.languageFilter.includes(option.value)}
                            onChange={() => toggleLanguage(option.value, realtime)}
                            type="checkbox"
                        />
                        <span>{t(option.labelKey)}</span>
                    </label>
                ))}
            </div>

            <label className="realtime-translate__field">
                <span>{t('realtimeTranslate.controls.prompt')}</span>
                <textarea
                    maxLength={600}
                    onBlur={realtime.saveConfig}
                    onChange={(event) => realtime.setPrompt(event.target.value)}
                    placeholder={t('realtimeTranslate.controls.promptPlaceholder')}
                    value={realtime.prompt}
                />
            </label>
        </>
    );
}

/**
 * Render stream metrics above the waterfall.
 *
 * @param {{ realtime: Record<string, unknown>, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
function StreamHeader({ realtime, t }) {
    return (
        <div className="realtime-translate__stream-header">
            <div>
                <p className="app-hero__eyebrow">{t('realtimeTranslate.stream.eyebrow')}</p>
                <h2 className="app-surface__title">{t('realtimeTranslate.stream.title')}</h2>
            </div>
            <div className="realtime-translate__metrics">
                <Metric
                    label={t('realtimeTranslate.metrics.session')}
                    value={realtime.sessionId?.slice(0, 8) || 'idle'}
                />
                <Metric
                    label={t('realtimeTranslate.metrics.chunks')}
                    value={String(realtime.metrics.chunks || 0)}
                />
                <Metric
                    label={t('realtimeTranslate.metrics.audio')}
                    value={formatBytes(realtime.metrics.bytes || 0)}
                />
                <Metric
                    label={t('realtimeTranslate.metrics.stt')}
                    value={realtime.metrics.stt || 'idle'}
                />
            </div>
        </div>
    );
}

/**
 * Render one compact stream metric.
 *
 * @param {{ label: string, value: string }} props
 * @returns {import('react').JSX.Element}
 */
function Metric({ label, value }) {
    return (
        <article>
            <span>{label}</span>
            <strong>{value}</strong>
        </article>
    );
}

/**
 * Render realtime transcript lines in a fixed-height waterfall.
 *
 * @param {{ lines: Array<Record<string, unknown>>, phase: string, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
function TranscriptWaterfall({ lines, phase, t }) {
    if (['checking', 'setup', 'preparing', 'connecting'].includes(phase)) {
        return (
            <div className="realtime-translate__waterfall">
                <div className="realtime-translate__loading" role="status">
                    <span aria-hidden="true" />
                    <strong>{statusTitle(t, phase)}</strong>
                    <small>{t('realtimeTranslate.stream.loadingText')}</small>
                </div>
            </div>
        );
    }

    if (!lines.length) {
        return (
            <div className="realtime-translate__waterfall">
                <div className="realtime-translate__empty">
                    <AppIcon name="headset" />
                    <span>{t('realtimeTranslate.stream.empty')}</span>
                </div>
            </div>
        );
    }

    return (
        <div className="realtime-translate__waterfall" aria-live="polite">
            {lines.map((line) => (
                <article className="realtime-translate__line" key={line.key}>
                    <div className="realtime-translate__line-meta">
                        <strong>{line.speaker}</strong>
                        <span>
                            {line.detectedLanguage || 'unknown'}
                            {typeof line.confidence === 'number'
                                ? ` · ${line.confidence.toFixed(2)}`
                                : ''}
                        </span>
                    </div>
                    <p>{line.text}</p>
                    {line.translatedText ? <small>{line.translatedText}</small> : null}
                </article>
            ))}
        </div>
    );
}

/**
 * Resolve the status title for the current workflow phase.
 *
 * @param {(key: string) => string} t
 * @param {string} phase
 * @returns {string}
 */
function statusTitle(t, phase) {
    const statusKey = `realtimeTranslate.phase.${phase}`;

    return t(statusKey) || phase;
}

/**
 * Toggle one accepted language.
 *
 * @param {string} language
 * @param {Record<string, unknown>} realtime
 * @returns {void}
 */
function toggleLanguage(language, realtime) {
    realtime.setLanguageFilter((languages) =>
        languages.includes(language)
            ? languages.filter((candidate) => candidate !== language)
            : [...languages, language],
    );
}

/**
 * Format audio byte counts.
 *
 * @param {number} bytes
 * @returns {string}
 */
function formatBytes(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return `${Math.round(bytes / 1024)} KB`;
}
