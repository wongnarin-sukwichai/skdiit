<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import TrackReviewersModal from '@/Components/Admin/Tracks/TrackReviewersModal.vue';
import { useI18n } from '@/i18n';
import { useAcademicTitles } from '@/composables/useAcademicTitles';

/**
 * The tracks side by side, each with its reviewers underneath. Clicking a track opens a modal to pick
 * its reviewers; ✕ removes one straight away. Reviewers without a track are listed below.
 */
const props = defineProps({
    tracks: { type: Array, required: true },
    reviewers: { type: Array, required: true }, // { id, name, email, academic_title_id, suspended, tracks: [ids] }
    readonly: { type: Boolean, default: false }, // view only (editors)
});

const { t, tr, formatNumber } = useI18n();
const { titleOf } = useAcademicTitles();

// One colour per track, by position (head = coloured card, soft = the list underneath)
const palette = [
    { head: 'bg-linear-to-br from-sky-500 to-sky-700 text-white', soft: 'border-sky-200 bg-sky-50/70 dark:border-sky-400/20 dark:bg-sky-400/5', dot: 'bg-sky-500' },
    { head: 'bg-linear-to-br from-emerald-500 to-emerald-700 text-white', soft: 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-400/20 dark:bg-emerald-400/5', dot: 'bg-emerald-500' },
    { head: 'bg-linear-to-br from-violet-500 to-violet-700 text-white', soft: 'border-violet-200 bg-violet-50/70 dark:border-violet-400/20 dark:bg-violet-400/5', dot: 'bg-violet-500' },
    { head: 'bg-linear-to-br from-amber-500 to-orange-600 text-white', soft: 'border-amber-200 bg-amber-50/70 dark:border-amber-400/20 dark:bg-amber-400/5', dot: 'bg-amber-500' },
    { head: 'bg-linear-to-br from-rose-500 to-rose-700 text-white', soft: 'border-rose-200 bg-rose-50/70 dark:border-rose-400/20 dark:bg-rose-400/5', dot: 'bg-rose-500' },
];
const columns = computed(() => props.tracks.map((track, i) => ({
    ...track,
    no: i + 1,
    color: palette[i % palette.length],
    members: props.reviewers.filter((r) => r.tracks.includes(track.id)),
})));
const unassigned = computed(() => props.reviewers.filter((r) => !r.tracks.length));

const editing = ref(null); // column whose modal is open
const busy = reactive({}); // `${trackId}-${reviewerId}` => saving

function remove(column, reviewer) {
    const key = `${column.id}-${reviewer.id}`;
    router.put(`/admin/tracks/${column.id}/reviewers`, { reviewers: column.members.filter((r) => r.id !== reviewer.id).map((r) => r.id) }, {
        only: ['reviewers', 'tracks', 'flash'],
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy[key] = true),
        onFinish: () => (busy[key] = false),
    });
}

const title = (reviewer) => (reviewer.academic_title_id ? `${titleOf(reviewer.academic_title_id)} ` : '');
</script>

<template>
    <div class="space-y-6">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.tracks.boardIntro') }}</p>

        <!-- One row: five columns on wide screens, scrolls sideways on smaller ones -->
        <div class="-mx-1 overflow-x-auto px-1 pb-2">
            <div class="grid min-w-280 grid-cols-5 gap-4">
                <section v-for="column in columns" :key="column.id" class="flex flex-col gap-3">
                    <component
                        :is="readonly ? 'div' : 'button'"
                        :type="readonly ? undefined : 'button'"
                        :class="[column.color.head, readonly ? '' : 'hover:-translate-y-0.5 hover:shadow-lg']"
                        class="group relative flex min-h-36 flex-col rounded-xl p-4 text-left shadow-sm transition"
                        :title="readonly ? undefined : t('admin.tracks.manageReviewers')"
                        @click="readonly || (editing = column)"
                    >
                        <span class="flex items-center justify-between">
                            <span class="grid size-8 place-items-center rounded-lg bg-white/20 text-sm font-bold">{{ column.no }}</span>
                            <span v-if="!readonly" class="grid size-8 place-items-center rounded-lg bg-white/0 text-sm opacity-70 transition group-hover:bg-white/20 group-hover:opacity-100">
                                <Fa icon="user-plus" />
                            </span>
                        </span>
                        <span class="mt-3 flex-1 leading-snug font-semibold">{{ tr({ en: column.name_en, th: column.name_th }) }}</span>
                        <span class="mt-3 flex items-center gap-1.5 text-xs opacity-90">
                            <Fa icon="users" /> {{ t('admin.tracks.reviewerCount', { count: formatNumber(column.members.length) }) }}
                        </span>
                    </component>

                    <TransitionGroup tag="ul" name="fade" :class="column.color.soft" class="flex-1 space-y-2 rounded-xl border p-2">
                        <li
                            v-for="reviewer in column.members"
                            :key="reviewer.id"
                            :class="busy[`${column.id}-${reviewer.id}`] && 'opacity-50'"
                            class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-white p-2.5 shadow-xs dark:border-white/10 dark:bg-ink-800"
                        >
                            <span :class="column.color.dot" class="size-2 shrink-0 rounded-full" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    <span class="text-neutral-500 dark:text-neutral-400">{{ title(reviewer) }}</span>{{ reviewer.name }}
                                </p>
                                <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ reviewer.email }}</p>
                                <span v-if="reviewer.suspended" class="mt-1 inline-block rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[0.7rem] text-red-700 dark:border-red-500/40 dark:bg-red-500/15 dark:text-red-300">
                                    {{ t('admin.registrants.suspended') }}
                                </span>
                            </div>
                            <button
                                v-if="!readonly"
                                type="button"
                                class="grid size-7 shrink-0 place-items-center rounded-md text-neutral-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                :title="t('admin.tracks.removeReviewer')"
                                :aria-label="t('admin.tracks.removeReviewer')"
                                :disabled="busy[`${column.id}-${reviewer.id}`]"
                                @click="remove(column, reviewer)"
                            >
                                <Fa icon="xmark" />
                            </button>
                        </li>
                        <li v-if="!column.members.length" key="empty" class="px-2 py-6 text-center text-xs text-red-600 dark:text-red-400">
                            <Fa icon="triangle-exclamation" class="mb-1 block w-full text-base" />
                            {{ t('admin.tracks.noReviewers') }}
                        </li>
                    </TransitionGroup>
                </section>
            </div>
        </div>

        <!-- Reviewers who cover no track never appear in the assignment picker -->
        <section class="rounded-xl border border-dashed border-amber-300 bg-amber-50/60 p-4 dark:border-amber-400/30 dark:bg-amber-400/5">
            <header class="mb-3 flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
                    <Fa icon="triangle-exclamation" />
                </span>
                <div>
                    <h3 class="font-semibold">
                        {{ t('admin.tracks.unassigned') }}
                        <span class="ml-1 text-sm font-normal text-neutral-500 dark:text-neutral-400">({{ formatNumber(unassigned.length) }})</span>
                    </h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.tracks.unassignedHint') }}</p>
                </div>
            </header>
            <Transition name="fade" mode="out-in">
                <TransitionGroup v-if="unassigned.length" key="list" tag="ul" name="fade" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <li v-for="reviewer in unassigned" :key="reviewer.id" class="flex items-center gap-3 rounded-lg border border-amber-200 bg-white p-2.5 dark:border-amber-400/20 dark:bg-ink-800">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-amber-100 text-xs text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
                            <Fa icon="user" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                <span class="text-neutral-500 dark:text-neutral-400">{{ title(reviewer) }}</span>{{ reviewer.name }}
                            </p>
                            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ reviewer.email }}</p>
                        </div>
                    </li>
                </TransitionGroup>
                <p v-else key="done" class="py-2 text-sm text-emerald-600 dark:text-emerald-400">
                    <Fa icon="circle-check" class="mr-1" /> {{ t('admin.tracks.allAssigned') }}
                </p>
            </Transition>
        </section>

        <TrackReviewersModal v-if="!readonly" :track="editing" :reviewers="reviewers" @close="editing = null" />
    </div>
</template>
