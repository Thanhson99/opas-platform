import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    createDouyinKeyword,
    crawlDouyinPreview,
    deleteDouyinVideo,
    getDouyinKeywords,
    getDouyinVideos,
    markDouyinVideoPosted,
    processDouyinSelected,
    updateDouyinVideoSelection,
} from '../services/douyin.service';

const defaultForm = {
    keyword: '美女跳舞',
    newKeyword: '',
    category: '',
    limit: 20,
};

/**
 * Own Douyin dashboard data loading and workflow actions.
 *
 * @returns {{
 *   form: { keyword: string, newKeyword: string, category: string, limit: number },
 *   keywords: Array<Record<string, unknown>>,
 *   previewVideos: Array<Record<string, unknown>>,
 *   listedVideos: Array<Record<string, unknown>>,
 *   currentJob: Record<string, unknown>|null,
 *   activeStatus: string,
 *   loading: boolean,
 *   actionLoading: string,
 *   error: string,
 *   metrics: { preview: number, selected: number, downloaded: number },
 *   setForm: import('react').Dispatch<import('react').SetStateAction<typeof defaultForm>>,
 *   setActiveStatus: (status: string) => void,
 *   selectKeyword: (keyword: string) => void,
 *   addKeyword: () => Promise<void>,
 *   crawlPreview: () => Promise<void>,
 *   toggleVideoSelection: (video: Record<string, unknown>, selected: boolean) => Promise<void>,
 *   selectAllPreview: (selected: boolean) => Promise<void>,
 *   processSelected: () => Promise<void>,
 *   markPosted: (video: Record<string, unknown>, deleteAfterPosted?: boolean) => Promise<void>,
 *   deleteVideo: (video: Record<string, unknown>) => Promise<void>,
 * }}
 */
export function useDouyinDashboard() {
    const [form, setForm] = useState(defaultForm);
    const [keywords, setKeywords] = useState([]);
    const [previewVideos, setPreviewVideos] = useState([]);
    const [listedVideos, setListedVideos] = useState([]);
    const [currentJob, setCurrentJob] = useState(null);
    const [activeStatus, setActiveStatus] = useState('preview');
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState('');
    const [error, setError] = useState('');

    const refreshKeywords = useCallback(async () => {
        const items = await getDouyinKeywords();
        setKeywords(items);

        if (!form.keyword && items[0]?.name) {
            setForm((value) => ({ ...value, keyword: String(items[0].name) }));
        }
    }, [form.keyword]);

    const refreshVideos = useCallback(async () => {
        setListedVideos(await getDouyinVideos({ status: activeStatus }));
    }, [activeStatus]);

    useEffect(() => {
        const loadInitialData = async () => {
            setLoading(true);

            try {
                await Promise.all([refreshKeywords(), refreshVideos()]);
                setError('');
            } catch (error) {
                setError(error.message || 'Unable to load Douyin dashboard data.');
            } finally {
                setLoading(false);
            }
        };

        void loadInitialData();
    }, [refreshKeywords, refreshVideos]);

    const selectKeyword = useCallback((keyword) => {
        setForm((value) => ({ ...value, keyword }));
    }, []);

    const addKeyword = useCallback(async () => {
        if (!form.newKeyword.trim()) {
            return;
        }

        setActionLoading('keyword');

        try {
            const keyword = await createDouyinKeyword({
                name: form.newKeyword,
                category: form.category,
            });
            setForm((value) => ({
                ...value,
                keyword: String(keyword.name ?? value.newKeyword),
                newKeyword: '',
                category: '',
            }));
            await refreshKeywords();
            setError('');
        } catch (error) {
            setError(error.message || 'Unable to create Douyin keyword.');
        } finally {
            setActionLoading('');
        }
    }, [form.category, form.newKeyword, refreshKeywords]);

    const crawlPreview = useCallback(async () => {
        setActionLoading('crawl');

        try {
            const job = await crawlDouyinPreview({
                keyword: form.keyword,
                limit: Number(form.limit) || 20,
            });
            setCurrentJob(job);
            setPreviewVideos(job.videos ?? []);
            setActiveStatus('preview');
            await refreshVideos();
            setError('');
        } catch (error) {
            setError(
                error.message ||
                    'Unable to crawl preview videos. Check login, captcha, and worker status.',
            );
        } finally {
            setActionLoading('');
        }
    }, [form.keyword, form.limit, refreshVideos]);

    const replaceVideo = useCallback((updatedVideo) => {
        setPreviewVideos((items) =>
            items.map((item) => (item.id === updatedVideo.id ? updatedVideo : item)),
        );
        setListedVideos((items) =>
            items.map((item) => (item.id === updatedVideo.id ? updatedVideo : item)),
        );
    }, []);

    const toggleVideoSelection = useCallback(
        async (video, selected) => {
            const updatedVideo = await updateDouyinVideoSelection(video.id, selected);
            replaceVideo(updatedVideo);
        },
        [replaceVideo],
    );

    const selectAllPreview = useCallback(
        async (selected) => {
            setActionLoading(selected ? 'select-all' : 'unselect-all');

            try {
                await Promise.all(
                    previewVideos.map((video) => updateDouyinVideoSelection(video.id, selected)),
                );
                setPreviewVideos((items) =>
                    items.map((item) => ({
                        ...item,
                        selected,
                        status: selected ? 'selected' : 'rejected',
                    })),
                );
                await refreshVideos();
                setError('');
            } catch (error) {
                setError(error.message || 'Unable to update selected videos.');
            } finally {
                setActionLoading('');
            }
        },
        [previewVideos, refreshVideos],
    );

    const processSelected = useCallback(async () => {
        if (!currentJob?.id) {
            return;
        }

        setActionLoading('process');

        try {
            const videos = await processDouyinSelected(currentJob.id);
            setPreviewVideos(videos);
            setActiveStatus('downloaded');
            await refreshVideos();
            setError('');
        } catch (error) {
            setError(
                error.message ||
                    'Unable to process selected videos. Download may need manual browser verification.',
            );
        } finally {
            setActionLoading('');
        }
    }, [currentJob?.id, refreshVideos]);

    const markPosted = useCallback(
        async (video, deleteAfterPosted = false) => {
            const updatedVideo = await markDouyinVideoPosted(video.id, deleteAfterPosted);
            replaceVideo(updatedVideo);
            await refreshVideos();
        },
        [refreshVideos, replaceVideo],
    );

    const deleteVideo = useCallback(
        async (video) => {
            await deleteDouyinVideo(video.id);
            setPreviewVideos((items) => items.filter((item) => item.id !== video.id));
            await refreshVideos();
        },
        [refreshVideos],
    );

    const metrics = useMemo(
        () => ({
            preview: previewVideos.length,
            selected: previewVideos.filter((video) => video.selected).length,
            downloaded: listedVideos.filter((video) => video.status === 'downloaded').length,
        }),
        [listedVideos, previewVideos],
    );

    return {
        form,
        keywords,
        previewVideos,
        listedVideos,
        currentJob,
        activeStatus,
        loading,
        actionLoading,
        error,
        metrics,
        setForm,
        setActiveStatus,
        selectKeyword,
        addKeyword,
        crawlPreview,
        toggleVideoSelection,
        selectAllPreview,
        processSelected,
        markPosted,
        deleteVideo,
    };
}
