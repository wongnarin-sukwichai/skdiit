import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

// Academic titles admin manages in Settings, shared by HandleInertiaRequests (backend pages only)
export function useAcademicTitles() {
    const page = usePage();
    const { tr } = useI18n();
    const titles = computed(() => page.props.academicTitles ?? []);

    /** Short title in the current language ("ผศ." / "Asst. Prof."), or '' when none is set. */
    const titleOf = (id) => {
        const title = titles.value.find((item) => item.id === id);
        return title ? tr(title.short) : '';
    };

    return {
        titles,
        titleOf,
        /** Name with its title in front, e.g. "ผศ. สมชาย ใจดี". */
        withTitle: (person) => [titleOf(person.academic_title_id), person.name].filter(Boolean).join(' '),
    };
}
