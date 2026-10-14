<script setup>
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

defineProps({
    show: { type: Boolean, default: false },
    icon: { type: String, default: 'circle-exclamation' },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: '' },
    danger: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'confirm']);

const { t } = useI18n();
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6 sm:p-8">
            <span
                :class="danger ? 'bg-red-500/15 text-red-400' : 'bg-gold-400/15 text-gold-400'"
                class="mb-4 grid size-12 place-items-center rounded-xl text-lg"
            >
                <Fa :icon="icon" />
            </span>
            <h2 class="mb-2 text-xl font-semibold">{{ title }}</h2>
            <p class="text-sm leading-relaxed text-neutral-300">{{ message }}</p>
            <slot />

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="button"
                    :class="danger ? 'bg-red-500 text-white hover:bg-red-400' : 'bg-gold-400 text-black hover:bg-gold-300'"
                    class="flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold transition disabled:opacity-60"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    <Fa :icon="icon" /> {{ confirmLabel || t('common.confirm') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
