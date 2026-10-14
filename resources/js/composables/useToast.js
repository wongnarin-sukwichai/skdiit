import { ref } from 'vue';

// One toast for the whole app; FlashToast shows it alongside server flash messages
const current = ref(null);

/** Show a toast from the client (e.g. mock actions that never reach the server). */
export function useToast() {
    return {
        current,
        /** `key` is an i18n key, `params` its placeholders */
        toast: (key, params = {}) => (current.value = { key, params, at: Date.now() }),
    };
}
