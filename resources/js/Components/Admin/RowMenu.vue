<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';

/**
 * "⋯" button with a dropdown of row actions. The menu is teleported to <body> with a fixed position,
 * so scrolling table wrappers (overflow-x-auto) do not clip it.
 * items: [{ key, label, icon, danger?, disabled?, title? }] — emits `select` with the item key.
 */
defineOptions({ inheritAttrs: false });
defineProps({
    items: { type: Array, required: true },
    label: { type: String, default: '' },
});
const emit = defineEmits(['select']);

const button = ref(null);
const menu = ref(null);
const open = ref(false);
const position = ref({ top: 0, left: 0 });

async function toggle() {
    if (open.value) return close();
    open.value = true;
    await nextTick();
    place();
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
    document.addEventListener('pointerdown', onOutside);
    document.addEventListener('keydown', onKeydown);
}

// Right-aligned under the button; flips above it near the bottom of the screen
function place() {
    const rect = button.value.getBoundingClientRect();
    const height = menu.value?.offsetHeight ?? 0;
    const width = menu.value?.offsetWidth ?? 0;
    const below = rect.bottom + 6 + height <= window.innerHeight;
    position.value = {
        top: below ? rect.bottom + 6 : rect.top - 6 - height,
        left: Math.max(8, rect.right - width),
    };
}

function close() {
    open.value = false;
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
    document.removeEventListener('pointerdown', onOutside);
    document.removeEventListener('keydown', onKeydown);
}

function onOutside(e) {
    if (!menu.value?.contains(e.target) && !button.value?.contains(e.target)) close();
}

function onKeydown(e) {
    if (e.key === 'Escape') close();
}

function choose(item) {
    if (item.disabled) return;
    close();
    emit('select', item.key);
}

onBeforeUnmount(close);
</script>

<template>
    <button ref="button" type="button" v-bind="$attrs" :title="label" :aria-label="label" :aria-expanded="open" @click="toggle">
        <slot><Fa icon="ellipsis" /></slot>
    </button>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="open"
                ref="menu"
                :style="{ top: `${position.top}px`, left: `${position.left}px` }"
                class="fixed z-40 min-w-56 overflow-hidden rounded-xl border border-neutral-200 bg-white py-1.5 text-sm text-neutral-800 shadow-xl dark:border-white/10 dark:bg-ink-800 dark:text-neutral-100"
                role="menu"
            >
                <button
                    v-for="item in items"
                    :key="item.key"
                    type="button"
                    role="menuitem"
                    :title="item.title"
                    :disabled="item.disabled"
                    :class="[
                        item.danger ? 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10' : 'hover:bg-neutral-100 dark:hover:bg-white/5',
                        item.disabled && 'cursor-not-allowed opacity-45 hover:bg-transparent dark:hover:bg-transparent',
                    ]"
                    class="flex w-full items-center gap-3 px-4 py-2 text-left transition"
                    @click="choose(item)"
                >
                    <Fa :icon="item.icon" class="w-4 text-neutral-400" :class="item.danger && 'text-current'" />
                    {{ item.label }}
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
