<script setup>
import { useI18n } from '@/i18n';

defineProps({
    label: { type: String, required: true },
    icon: { type: String, default: null },
    error: { type: String, default: null },
});

const { t } = useI18n();

// Server errors arrive as translation keys (see AuthController::MESSAGES)
const message = (error) => (/^(validation|auth)\./.test(error) ? t(error) : error);
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-sm text-neutral-300">{{ label }}</span>
        <span class="relative block">
            <Fa v-if="icon" :icon="icon" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-sm text-neutral-500" />
            <slot :input-class="[
                'w-full rounded-lg border bg-ink-800 py-2.5 pr-3 text-sm text-white placeholder-neutral-500 outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20',
                icon ? 'pl-10' : 'pl-3',
                error ? 'border-red-500/70' : 'border-white/10',
            ]" />
        </span>
        <Transition name="fade">
            <span v-if="error" class="mt-1 block text-xs text-red-400">{{ message(error) }}</span>
        </Transition>
    </label>
</template>
