<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'max-w-md' },
});
const emit = defineEmits(['close']);

function onKeydown(e) {
    if (e.key === 'Escape' && props.show) emit('close');
}

watch(() => props.show, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    open ? window.addEventListener('keydown', onKeydown) : window.removeEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="emit('close')" />
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div
                        :class="maxWidth"
                        class="modal-panel dark relative w-full overflow-hidden rounded-2xl border border-gold-400/25 bg-ink-900 text-neutral-100 shadow-[0_0_60px_rgba(245,184,0,.15)]"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div class="glow-line absolute inset-x-0 top-0" />
                        <button
                            class="absolute top-4 right-4 grid size-8 place-items-center rounded-full text-neutral-400 transition hover:bg-white/10 hover:text-white"
                            :aria-label="$t('common.close')"
                            @click="emit('close')"
                        >
                            <Fa icon="xmark" />
                        </button>
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
