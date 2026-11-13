import { ref } from 'vue';

/*
 * The email an editor's decision would send (2026-11-13, SubmissionController::mailPreview), for the confirm dialogs of
 * screening, the decision after review and the camera-ready check: load(params) now, or later() while typing.
 */
export function useMailPreview(submissionId) {
    const preview = ref(null);
    const loading = ref(false);
    let timer = null;

    async function load(params) {
        loading.value = true;
        const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== '' && v !== false).map(([k, v]) => [k, v === true ? '1' : String(v)]));
        try {
            const response = await fetch(`/admin/submissions/${submissionId}/mail-preview?${query}`, { headers: { Accept: 'application/json' } });
            preview.value = response.ok ? await response.json() : null;
        } catch {
            preview.value = null;
        } finally {
            loading.value = false;
        }
    }
    function later(params) {
        clearTimeout(timer);
        timer = setTimeout(() => load(params), 400);
    }

    return { preview, loading, load, later };
}
