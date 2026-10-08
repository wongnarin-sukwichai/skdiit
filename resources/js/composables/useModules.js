import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Backend modules the signed-in user can open, shared by HandleInertiaRequests (config/modules.php)
export function useModules() {
    const page = usePage();
    const modules = computed(() => page.props.auth.modules ?? []);

    return {
        modules,
        /** 'full' | 'view' | 'own', or null when the user cannot use the module */
        accessTo: (key) => modules.value.find((m) => m.key === key)?.access ?? null,
    };
}
