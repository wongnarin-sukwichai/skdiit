<script setup>
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({
    tracks: { type: Array, required: true },
});

const { t, formatNumber } = useI18n();

// Editable copy of each track's names, refreshed when the server sends new data
const drafts = reactive({});
const errors = reactive({});
const busy = reactive({});
watch(() => props.tracks, (tracks) => {
    tracks.forEach((track) => (drafts[track.id] = { name_en: track.name_en, name_th: track.name_th }));
}, { immediate: true });

const isDirty = (track) => drafts[track.id].name_en !== track.name_en || drafts[track.id].name_th !== track.name_th;

function save(track) {
    router.put(`/admin/settings/tracks/${track.id}`, drafts[track.id], {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy[track.id] = true),
        onSuccess: () => (errors[track.id] = {}),
        onError: (e) => (errors[track.id] = e),
        onFinish: () => (busy[track.id] = false),
    });
}

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.tracks.intro') }}</p>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="w-12 px-4 py-3 font-medium">#</th>
                        <th class="px-4 py-3 font-medium">{{ t('admin.settings.tracks.nameEn') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('admin.settings.tracks.nameTh') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('admin.settings.tracks.reviewers') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('common.save') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="(track, i) in tracks" :key="track.id">
                        <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400">{{ i + 1 }}</td>
                        <td class="px-4 py-3">
                            <input v-model="drafts[track.id].name_en" :class="[input, border(errors[track.id]?.name_en)]" @keydown.enter="isDirty(track) && save(track)">
                        </td>
                        <td class="px-4 py-3">
                            <input v-model="drafts[track.id].name_th" :class="[input, border(errors[track.id]?.name_th)]" @keydown.enter="isDirty(track) && save(track)">
                        </td>
                        <td class="px-4 py-3 text-center">{{ formatNumber(track.reviewers) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button
                                class="inline-flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2 text-xs font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                                :disabled="!isDirty(track) || busy[track.id]"
                                @click="save(track)"
                            >
                                <Fa icon="floppy-disk" /> {{ t('common.save') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
