<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({
    reviewers: { type: Array, required: true },
    tracks: { type: Array, required: true },
});
defineEmits(['goUsers']);

const { t, tr } = useI18n();
const busy = reactive({});

// Each click saves straight away
function toggle(reviewer, trackId) {
    const tracks = reviewer.tracks.includes(trackId)
        ? reviewer.tracks.filter((id) => id !== trackId)
        : [...reviewer.tracks, trackId];

    router.put(`/admin/settings/reviewers/${reviewer.id}/tracks`, { tracks }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy[reviewer.id] = true),
        onFinish: () => (busy[reviewer.id] = false),
    });
}

const trackName = (track) => tr({ en: track.name_en, th: track.name_th });
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.reviewers.intro') }}</p>

        <div v-if="!reviewers.length" class="flex flex-col items-center rounded-xl border border-dashed border-neutral-300 py-12 text-center dark:border-white/15">
            <Fa icon="user-check" class="mb-3 text-3xl text-gold-400" />
            <p class="max-w-md text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.reviewers.empty') }}</p>
            <button class="mt-4 text-sm font-medium text-gold-600 hover:underline dark:text-gold-400" @click="$emit('goUsers')">
                {{ t('admin.settings.tabs.users') }} <Fa icon="chevron-right" class="text-xs" />
            </button>
        </div>

        <ul v-else class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
            <li v-for="reviewer in reviewers" :key="reviewer.id" class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
                <div class="min-w-0 lg:w-72">
                    <p class="truncate font-medium">{{ reviewer.name }}</p>
                    <p class="truncate text-sm text-neutral-500 dark:text-neutral-400">{{ reviewer.email }}</p>
                </div>
                <div class="flex flex-1 flex-wrap gap-2" :class="busy[reviewer.id] ? 'pointer-events-none opacity-60' : ''">
                    <button
                        v-for="track in tracks"
                        :key="track.id"
                        :aria-pressed="reviewer.tracks.includes(track.id)"
                        :class="reviewer.tracks.includes(track.id)
                            ? 'border-gold-400 bg-gold-400 text-black'
                            : 'border-neutral-300 text-neutral-600 hover:border-gold-400 dark:border-white/15 dark:text-neutral-300'"
                        class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                        @click="toggle(reviewer, track.id)"
                    >
                        <Fa :icon="reviewer.tracks.includes(track.id) ? 'check' : 'plus'" class="text-[0.65rem]" />
                        {{ trackName(track) }}
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
