<script setup>
defineProps({
    checked: { type: Boolean, default: false },
});
</script>

<template>
    <span
        :class="checked ? 'border-gold-400 bg-gold-400 text-black' : 'border-neutral-300 dark:border-white/30'"
        class="inline-grid size-5 place-items-center rounded border-2 align-middle"
        role="img"
        :aria-label="checked ? $t('common.yes') : $t('common.no')"
    >
        <Fa v-if="checked" icon="check" class="text-[0.7rem]" />
    </span>
</template>
