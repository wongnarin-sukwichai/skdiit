import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Conference information and public-site texts admin edits in Settings (App\Support\SiteContent),
 * shared with every page as the `site` prop: { conference, home, about, contact }. Bilingual values are { th, en }.
 */
export function useSite() {
    return computed(() => usePage().props.site);
}
