<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

// Shown on every page (admin and public layouts) while an admin views as another user (App\Support\Impersonation)
const page = usePage();
const { t } = useI18n();
const info = computed(() => page.props.impersonation);

// Minutes left before the view ends by itself; refreshed every 30 seconds
const now = ref(Date.now());
let timer;
onMounted(() => (timer = setInterval(() => (now.value = Date.now()), 30000)));
onBeforeUnmount(() => clearInterval(timer));
const minutesLeft = computed(() => Math.max(0, Math.ceil((new Date(info.value.endsAt) - now.value) / 60000)));

const leaving = ref(false);
function stop() {
    router.post('/impersonation/stop', {}, { onStart: () => (leaving.value = true), onFinish: () => (leaving.value = false) });
}
</script>

<template>
    <Transition name="fade">
        <div
            v-if="info"
            class="fixed bottom-4 left-1/2 z-50 flex w-[calc(100%-2rem)] max-w-3xl -translate-x-1/2 flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-red-400/60 bg-red-600 px-5 py-3 text-sm text-white shadow-[0_10px_40px_rgba(220,38,38,.45)]"
            role="status"
        >
            <Fa icon="user-secret" class="text-lg" />
            <p class="min-w-0 flex-1">
                <span class="font-semibold">{{ t('impersonation.viewingAs', { name: info.name }) }}</span>
                <span class="text-red-100"> ({{ t(`admin.role.${info.role}`) }} · {{ info.email }})</span>
                <span class="block text-xs text-red-100">{{ t('impersonation.readOnlyHint', { minutes: minutesLeft }) }}</span>
            </p>
            <button
                type="button"
                class="flex items-center gap-2 rounded-lg bg-white px-4 py-2 font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-60"
                :disabled="leaving"
                @click="stop"
            >
                <Fa icon="arrow-right-from-bracket" /> {{ t('impersonation.stop') }}
            </button>
        </div>
    </Transition>
</template>
