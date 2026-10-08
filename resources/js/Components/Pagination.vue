<script setup>
import { computed } from 'vue';

const props = defineProps({
    lastPage: { type: Number, required: true },
});
const page = defineModel({ type: Number, required: true });

// e.g. 1 2 3 4 5 … 26, keeping the current page visible
const items = computed(() => {
    const last = props.lastPage;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);

    const start = Math.max(1, Math.min(page.value - 2, last - 5));
    const end = Math.min(last, start + 4);
    const list = [];
    if (start > 1) list.push(1, start > 2 ? '…' : null);
    for (let p = start; p <= end; p++) list.push(p);
    if (end < last) list.push(end < last - 1 ? '…' : null, last);
    return list.filter((p) => p !== null);
});

const go = (p) => (page.value = Math.min(props.lastPage, Math.max(1, p)));

const base = 'grid h-9 min-w-9 place-items-center rounded-md px-2 text-sm transition';
const idle = 'border border-ink-600/60 text-neutral-500 hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-300';
</script>

<template>
    <nav class="flex items-center gap-1.5" aria-label="Pagination">
        <button :class="[base, idle, 'disabled:opacity-40']" :disabled="page === 1" @click="go(page - 1)">
            <Fa icon="chevron-left" class="text-xs" />
        </button>
        <template v-for="(item, i) in items" :key="i">
            <span v-if="item === '…'" class="px-1 text-neutral-400">…</span>
            <button
                v-else
                :class="[base, item === page ? 'bg-gold-400 font-semibold text-black shadow-[0_0_12px_rgba(255,204,41,.35)]' : idle]"
                @click="go(item)"
            >
                {{ item }}
            </button>
        </template>
        <button :class="[base, idle, 'disabled:opacity-40']" :disabled="page === lastPage" @click="go(page + 1)">
            <Fa icon="chevron-right" class="text-xs" />
        </button>
    </nav>
</template>
