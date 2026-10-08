<script setup>
defineProps({
    icon: { type: String, required: true },
    title: { type: String, required: true },
    lead: { type: String, default: '' },
});
</script>

<template>
    <section class="mx-auto max-w-[1600px] px-4 pt-12 pb-8 sm:px-6 lg:px-8">
        <p class="mb-2 text-xs font-semibold tracking-[0.35em] text-gold-400 uppercase">SKDIIT 2027</p>
        <h1 class="flex items-center gap-3 text-3xl font-bold text-white sm:text-4xl">
            <Fa :icon="icon" class="text-gold-400" />
            {{ title }}
        </h1>
        <p v-if="lead" class="mt-3 max-w-3xl text-neutral-300">{{ lead }}</p>
    </section>
</template>
