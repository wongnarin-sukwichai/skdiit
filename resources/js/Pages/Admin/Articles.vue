<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { formatFileSize } from '@/utils/format';
import { articleArchive, mockTracks } from '@/data/mock';

/*
 * Article archive (admin only, Archive group): finished papers of every conference year,
 * published in the Proceeding (Option A) or a journal (Option B). Mock until wired to real data.
 */
const { t, tr, formatNumber } = useI18n();
const { toast } = useToast();

const years = [...new Set(articleArchive.map((a) => a.year))];
const blank = { q: '', year: '', track: '', type: '' };
const form = reactive({ ...blank });
const applied = reactive({ ...blank });
const page = ref(1);
const perPage = 10;

const normalize = (text) => String(text ?? '').toLowerCase();
const filtered = computed(() => {
    const q = normalize(applied.q.trim());
    return articleArchive.filter((a) => (!applied.year || a.year === Number(applied.year))
        && (!applied.track || a.track.id === Number(applied.track))
        && (!applied.type || a.type === applied.type)
        && (!q || normalize(`${a.code} ${a.title.en} ${a.title.th} ${a.keywords.en} ${a.keywords.th} ${a.authors.map((x) => `${x.name.en} ${x.name.th}`).join(' ')}`).includes(q)));
});
const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));
const rows = computed(() => filtered.value.slice((page.value - 1) * perPage, page.value * perPage));
watch(lastPage, (last) => page.value > last && (page.value = last));

function search() {
    Object.assign(applied, form);
    page.value = 1;
}
function reset() {
    Object.assign(form, blank);
    search();
}

const stats = {
    articles: articleArchive.length,
    proceeding: articleArchive.filter((a) => a.type === 'proceeding').length,
    journal: articleArchive.filter((a) => a.type === 'journal').length,
    years: years.length,
};
const statCards = [
    { key: 'articles', icon: 'database' },
    { key: 'proceeding', icon: 'book' },
    { key: 'journal', icon: 'newspaper' },
    { key: 'years', icon: 'calendar-days' },
];

const authorList = (article) => article.authors.map((a) => tr(a.name)).join(', ');
const where = (article) => (article.type === 'journal'
    ? `${article.journal.name}, ${article.journal.issue}`
    : t('admin.articles.inProceeding', { year: article.year, pages: article.pages }));

// APA-like reference, copied for reports and CVs
function citation(article) {
    return `${article.authors.map((a) => tr(a.name)).join(', ')} (${article.year}). ${tr(article.title)}. ${where(article)}.`;
}
async function copyCitation(article) {
    try {
        await navigator.clipboard.writeText(citation(article));
        toast('admin.articles.toast.copied');
    } catch {
        toast('admin.articles.toast.copyFailed');
    }
}

const viewing = ref(null);
const shown = ref(null);
watch(viewing, (value) => value && (shown.value = value));

const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 py-2.5 pr-3 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
const smallBtn = 'inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium whitespace-nowrap transition hover:border-gold-400 hover:text-gold-600 dark:border-white/15 dark:hover:text-gold-400';
const typeBadge = {
    proceeding: 'border-gold-300 bg-gold-50 text-gold-700 dark:border-gold-400/40 dark:bg-gold-400/10 dark:text-gold-300',
    journal: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/15 dark:text-sky-300',
};
</script>

<template>
    <Head :title="t('admin.menu.articles')" />

    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="database" :title="t('admin.menu.articles')" :subtitle="t('admin.articles.subtitle')" />
            <StatCards :stats="stats" :cards="statCards" />
        </Card>

        <Card>
            <form class="mb-5 grid gap-3 md:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_1fr_auto_auto]" @submit.prevent="search">
                <label class="relative md:col-span-2 xl:col-span-1">
                    <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                    <input v-model="form.q" :placeholder="t('admin.articles.search')" :class="[input, 'pl-11']">
                </label>
                <select v-model="form.year" :class="[input, 'pl-3']" :aria-label="t('admin.articles.year')">
                    <option value="">{{ t('admin.articles.allYears') }}</option>
                    <option v-for="year in years" :key="year" :value="year">SKDIIT {{ year }}</option>
                </select>
                <select v-model="form.track" :class="[input, 'pl-3']" :aria-label="t('submissions.fields.track')">
                    <option value="">{{ t('admin.accounts.allTracks') }}</option>
                    <option v-for="track in mockTracks" :key="track.id" :value="track.id">{{ tr(track.name) }}</option>
                </select>
                <select v-model="form.type" :class="[input, 'pl-3']" :aria-label="t('admin.articles.type')">
                    <option value="">{{ t('admin.articles.allTypes') }}</option>
                    <option v-for="type in ['proceeding', 'journal']" :key="type" :value="type">{{ t(`admin.articles.types.${type}`) }}</option>
                </select>
                <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-5 py-2.5 transition hover:border-gold-400 dark:border-white/20" @click="reset">
                    <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
                </button>
                <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-6 py-2.5 font-semibold text-black transition hover:bg-gold-300">
                    <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
                </button>
            </form>

            <p class="mb-3 text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.articles.found', { count: formatNumber(filtered.length) }) }}</p>

            <Transition name="fade" mode="out-in">
                <ul :key="`${page}-${filtered.length}`" class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                    <li v-for="article in rows" :key="article.id" class="flex flex-col gap-3 p-4 transition hover:bg-gold-50 sm:flex-row sm:items-start dark:hover:bg-gold-400/5">
                        <div class="grid size-14 shrink-0 place-items-center rounded-lg bg-neutral-100 text-center dark:bg-white/5">
                            <span class="text-xs leading-tight text-neutral-500 dark:text-neutral-400">SKDIIT<br><b class="text-sm text-neutral-800 dark:text-neutral-100">{{ article.year }}</b></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs text-gold-600 dark:text-gold-400">{{ article.code }}</span>
                                <span :class="typeBadge[article.type]" class="rounded-full border px-2.5 py-0.5 text-xs font-medium">{{ t(`admin.articles.types.${article.type}`) }}</span>
                            </div>
                            <button type="button" class="text-left font-semibold hover:text-gold-600 dark:hover:text-gold-400" @click="viewing = article">{{ tr(article.title) }}</button>
                            <p class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-300">{{ authorList(article) }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ where(article) }} · {{ tr(article.track.name) }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                            <button :class="smallBtn" @click="toast('admin.publication.toast.download')"><Fa icon="download" /> PDF · {{ formatFileSize(article.file.size) }}</button>
                            <button :class="smallBtn" @click="copyCitation(article)"><Fa icon="copy" /> {{ t('admin.articles.cite') }}</button>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="download" class="mr-1" />{{ t('admin.articles.downloads', { count: formatNumber(article.downloads) }) }}</span>
                        </div>
                    </li>
                    <li v-if="!rows.length" class="px-4 py-12 text-center text-neutral-500">{{ t('admin.articles.empty') }}</li>
                </ul>
            </Transition>

            <div class="mt-5 flex justify-end">
                <Pagination v-model="page" :last-page="lastPage" />
            </div>

            <p class="mt-4 text-xs text-neutral-500 dark:text-neutral-400">
                <Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.articles.mockNote') }}
            </p>
        </Card>
    </div>

    <Modal :show="!!viewing" max-width="max-w-2xl" @close="viewing = null">
        <div v-if="shown" class="space-y-5 p-6 sm:p-8">
            <div>
                <p class="font-mono text-xs text-gold-400">{{ shown.code }} · SKDIIT {{ shown.year }}</p>
                <h3 class="mt-1 text-xl font-semibold">{{ tr(shown.title) }}</h3>
                <p class="mt-1 text-sm text-neutral-400">{{ where(shown) }}</p>
            </div>
            <div>
                <p class="mb-1 text-sm text-neutral-400">{{ t('admin.articles.authors') }}</p>
                <ol class="list-decimal space-y-0.5 pl-5 text-sm">
                    <li v-for="(author, i) in shown.authors" :key="i">{{ tr(author.name) }} <span class="text-neutral-400">· {{ tr(author.affiliation) }}</span></li>
                </ol>
            </div>
            <div>
                <p class="mb-1 text-sm text-neutral-400">{{ t('submissions.fields.abstract') }}</p>
                <p class="text-sm leading-relaxed text-neutral-200">{{ tr(shown.abstract) }}</p>
            </div>
            <div>
                <p class="mb-1 text-sm text-neutral-400">{{ t('submissions.fields.keywords') }}</p>
                <p class="text-sm">{{ tr(shown.keywords) }}</p>
            </div>
            <div class="rounded-lg border border-white/10 bg-white/5 p-3 text-xs text-neutral-300">
                <p class="mb-1 text-neutral-400">{{ t('admin.articles.citation') }}</p>
                {{ citation(shown) }}
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" class="flex items-center gap-2 rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="copyCitation(shown)">
                    <Fa icon="copy" /> {{ t('admin.articles.cite') }}
                </button>
                <button type="button" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="toast('admin.publication.toast.download')">
                    <Fa icon="download" /> {{ t('admin.publication.download') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
