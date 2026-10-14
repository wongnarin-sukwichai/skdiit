<script setup>
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

// Shown when an author tries to submit before the opening date or after the deadline
const props = defineProps({
    show: { type: Boolean, default: false },
    phase: { type: String, required: true }, // upcoming | closed
    opensAt: { type: String, default: null },
    deadline: { type: String, default: null },
});
const emit = defineEmits(['close']);

const { t, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6 sm:p-8">
            <span
                :class="phase === 'closed' ? 'bg-red-500/15 text-red-400' : 'bg-sky-400/15 text-sky-300'"
                class="mb-4 grid size-12 place-items-center rounded-xl text-lg"
            >
                <Fa :icon="phase === 'closed' ? 'lock' : 'calendar-days'" />
            </span>
            <h2 class="mb-2 text-xl font-semibold">{{ t(`submissions.deadline.alert.${props.phase}.title`) }}</h2>
            <p class="text-sm leading-relaxed text-neutral-300">
                {{ t(`submissions.deadline.alert.${props.phase}.message`, { date: when(props.phase === 'closed' ? props.deadline : props.opensAt) }) }}
            </p>
            <div class="mt-6 flex justify-end">
                <button type="button" class="rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="emit('close')">
                    {{ t('common.ok') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
