<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { useAcademicTitles } from '@/composables/useAcademicTitles';

/**
 * Tick the reviewers who cover one track, then save (TrackController::updateReviewers).
 * track: { id, name_en, name_th, color } or null when closed
 */
const props = defineProps({
    track: { type: Object, default: null },
    reviewers: { type: Array, required: true },
});
const emit = defineEmits(['close']);

const { t, tr, formatNumber } = useI18n();
const { titleOf } = useAcademicTitles();

// Keep the last track so the content stays visible while the modal fades out
const shown = ref(null);
const selected = ref(new Set());
const search = ref('');
const saving = ref(false);

watch(() => props.track, (track) => {
    if (!track) return;
    shown.value = track;
    selected.value = new Set(props.reviewers.filter((r) => r.tracks.includes(track.id)).map((r) => r.id));
    search.value = '';
}, { immediate: true });

const normalize = (text) => String(text ?? '').toLowerCase();
const visible = computed(() => props.reviewers.filter((r) => normalize(`${r.name} ${r.email}`).includes(normalize(search.value).trim())));

function toggle(id) {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
}

function save() {
    router.put(`/admin/tracks/${shown.value.id}/reviewers`, { reviewers: [...selected.value] }, {
        only: ['reviewers', 'tracks', 'flash'],
        preserveScroll: true,
        preserveState: true,
        onStart: () => (saving.value = true),
        onSuccess: () => emit('close'),
        onFinish: () => (saving.value = false),
    });
}

const title = (reviewer) => (reviewer.academic_title_id ? `${titleOf(reviewer.academic_title_id)} ` : '');
</script>

<template>
    <Modal :show="!!track" max-width="max-w-lg" @close="emit('close')">
        <form v-if="shown" @submit.prevent="save">
            <header :class="shown.color.head" class="px-6 pt-6 pb-5 sm:px-8">
                <p class="text-xs font-medium tracking-wider uppercase opacity-80">{{ t('admin.tracks.manageReviewers') }}</p>
                <h2 class="mt-1 pr-8 text-xl leading-snug font-semibold">{{ tr({ en: shown.name_en, th: shown.name_th }) }}</h2>
            </header>

            <div class="space-y-4 p-6 sm:px-8">
                <label class="relative block">
                    <Fa icon="magnifying-glass" class="absolute top-1/2 left-3.5 -translate-y-1/2 text-sm text-neutral-500" />
                    <input
                        v-model="search"
                        :placeholder="t('admin.accounts.search')"
                        class="w-full rounded-lg border border-white/10 bg-ink-800 py-2.5 pr-3 pl-10 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20"
                    >
                </label>

                <ul class="max-h-80 space-y-1.5 overflow-y-auto pr-1">
                    <li v-for="reviewer in visible" :key="reviewer.id">
                        <label
                            :class="selected.has(reviewer.id) ? 'border-gold-400 bg-gold-400/10' : 'border-white/10 hover:border-white/25'"
                            class="flex items-center gap-3 rounded-lg border px-3 py-2.5 transition"
                        >
                            <input type="checkbox" class="size-4 shrink-0 accent-gold-400" :checked="selected.has(reviewer.id)" @change="toggle(reviewer.id)">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">
                                    <span class="text-neutral-400">{{ title(reviewer) }}</span>{{ reviewer.name }}
                                </span>
                                <span class="block truncate text-xs text-neutral-400">{{ reviewer.email }}</span>
                            </span>
                            <span v-if="reviewer.suspended" class="shrink-0 rounded-full border border-red-500/40 bg-red-500/15 px-2 py-0.5 text-[0.7rem] text-red-300">
                                {{ t('admin.registrants.suspended') }}
                            </span>
                            <span v-else-if="reviewer.tracks.length && !reviewer.tracks.includes(shown.id)" class="shrink-0 text-[0.7rem] text-neutral-500">
                                {{ t('admin.tracks.otherTracks', { count: formatNumber(reviewer.tracks.length) }) }}
                            </span>
                        </label>
                    </li>
                    <li v-if="!visible.length" class="py-8 text-center text-sm text-neutral-500">
                        {{ reviewers.length ? t('admin.accounts.empty') : t('admin.tracks.noReviewerAccounts') }}
                    </li>
                </ul>
            </div>

            <footer class="flex items-center justify-between gap-3 border-t border-white/10 px-6 py-4 sm:px-8">
                <span class="text-sm text-neutral-400">{{ t('admin.tracks.selectedCount', { count: formatNumber(selected.size) }) }}</span>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="saving">
                        <Fa icon="floppy-disk" /> {{ t('common.save') }}
                    </button>
                </div>
            </footer>
        </form>
    </Modal>
</template>
