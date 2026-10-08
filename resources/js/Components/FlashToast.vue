<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const page = usePage();
const { t } = useI18n();
const message = ref(null);
let timer;

watch(() => page.props.flash?.success, (key) => {
    if (!key) return;
    message.value = key;
    clearTimeout(timer);
    timer = setTimeout(() => (message.value = null), 4000);
}, { immediate: true });
</script>

<template>
    <Transition name="fade">
        <div
            v-if="message"
            class="fixed top-5 left-1/2 z-[60] flex -translate-x-1/2 items-center gap-3 rounded-xl border border-gold-400/40 bg-ink-900/95 px-5 py-3 text-sm text-white shadow-lg backdrop-blur"
            role="status"
        >
            <Fa icon="circle-check" class="text-gold-400" />
            {{ t(message) }}
        </div>
    </Transition>
</template>
