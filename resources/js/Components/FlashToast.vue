<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';

const page = usePage();
const { t } = useI18n();
const { current } = useToast();
const message = ref(null);
let timer;

function show(key, params = {}, kind = 'success') {
    message.value = { key, params, kind };
    clearTimeout(timer);
    timer = setTimeout(() => (message.value = null), 4000);
}

watch(() => page.props.flash?.success, (key) => key && show(key), { immediate: true });
watch(() => page.props.flash?.error, (key) => key && show(key, {}, 'error'), { immediate: true });
watch(current, (value) => value && show(value.key, value.params));
</script>

<template>
    <Transition name="fade">
        <div
            v-if="message"
            :class="message.kind === 'error' ? 'border-red-400/60' : 'border-gold-400/40'"
            class="fixed top-5 left-1/2 z-60 flex -translate-x-1/2 items-center gap-3 rounded-xl border bg-ink-900/95 px-5 py-3 text-sm text-white shadow-lg backdrop-blur"
            role="status"
        >
            <Fa :icon="message.kind === 'error' ? 'circle-exclamation' : 'circle-check'" :class="message.kind === 'error' ? 'text-red-400' : 'text-gold-400'" />
            {{ t(message.key, message.params) }}
        </div>
    </Transition>
</template>
