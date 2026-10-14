import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

/** users.affiliation value meaning "typed my own" (the name is in affiliation_other). */
export const OTHER_AFFILIATION = 'other';

// Affiliations admin manages in Settings, shared by HandleInertiaRequests on every page
export function useAffiliations() {
    const page = usePage();
    const { tr } = useI18n();
    const affiliations = computed(() => page.props.affiliations ?? []);

    return {
        affiliations,
        /** Display name for a user/registrant: the list name, or what they typed under "Other". */
        affiliationOf: (person) => {
            if (!person?.affiliation) return '';
            if (person.affiliation === OTHER_AFFILIATION) return person.affiliation_other ?? '';
            const found = affiliations.value.find((a) => a.code === person.affiliation);
            return found ? tr(found.name) : person.affiliation;
        },
    };
}
