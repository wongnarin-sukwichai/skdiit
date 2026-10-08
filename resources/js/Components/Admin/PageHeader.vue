<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({
    icon: { type: String, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

const { t } = useI18n();
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
        <div class="flex items-center gap-4">
            <Fa :icon="icon" class="text-4xl text-gold-400" />
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">{{ title }}</h1>
                <p v-if="subtitle" class="text-sm text-neutral-500 dark:text-neutral-400">{{ subtitle }}</p>
            </div>
        </div>
        <nav class="flex items-center gap-2 text-sm text-neutral-500 sm:ml-auto dark:text-neutral-400" aria-label="Breadcrumb">
            <Link href="/admin" class="flex items-center gap-1.5 hover:text-gold-500">
                <Fa icon="house" class="text-gold-400" /> {{ t('admin.menu.dashboard') }}
            </Link>
            <template v-if="title !== t('admin.menu.dashboard')">
                <Fa icon="chevron-right" class="text-[0.6rem]" />
                <span>{{ title }}</span>
            </template>
        </nav>
    </div>
</template>
