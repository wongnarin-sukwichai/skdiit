import { computed, reactive, ref, watch } from 'vue';
import { affiliationByKey, registrants } from '@/data/mock';

const normalize = (text) => String(text ?? '').toLowerCase().trim();

/**
 * Search, page-size and pagination state for the registrant list (frontend and backend share it).
 * The backend adds its own fields via `filters` (initial values) and `match(row, applied)`,
 * and `withEmail` lets the name box match emails too (never on the public list).
 */
export function useRegistrantTable(source = registrants, { filters = {}, match = () => true, withEmail = false } = {}) {
    const blank = { name: '', affiliation: '', ...filters };
    const form = reactive({ ...blank });
    const applied = reactive({ ...blank });
    const perPage = ref(10);
    const page = ref(1);

    // Names and affiliations match in both languages
    const filtered = computed(() => {
        const name = normalize(applied.name);
        const affiliation = normalize(applied.affiliation);

        return source.filter((row) => {
            const aff = affiliationByKey[row.affiliation];
            return (!name || normalize(`${row.name.en} ${row.name.th} ${withEmail ? row.email : ''}`).includes(name))
                && (!affiliation || normalize(`${aff?.en} ${aff?.th}`).includes(affiliation))
                && match(row, applied);
        });
    });

    const total = computed(() => filtered.value.length);
    const lastPage = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
    const rows = computed(() => {
        const start = (page.value - 1) * perPage.value;
        return filtered.value.slice(start, start + perPage.value).map((row, i) => ({ ...row, no: start + i + 1 }));
    });

    watch(perPage, () => (page.value = 1));
    // Stay on a valid page when rows are removed
    watch(lastPage, (last) => page.value > last && (page.value = last));

    function search() {
        Object.assign(applied, form);
        page.value = 1;
    }

    function reset() {
        Object.assign(form, blank);
        search();
    }

    return { form, applied, perPage, page, total, lastPage, rows, filtered, search, reset };
}
