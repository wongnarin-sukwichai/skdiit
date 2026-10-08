<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';

const props = defineProps({
    section: { type: String, required: true },
});

const { t } = useI18n();
const { modules } = useModules();

const icon = computed(() => modules.value.find((m) => m.key === props.section)?.icon);
const title = computed(() => t(`admin.menu.${props.section}`));
</script>

<template>
    <Head :title="title" />

    <Card class="space-y-10">
        <PageHeader :icon="icon" :title="title" />
        <div class="flex flex-col items-center py-16 text-center">
            <span class="mb-5 grid size-20 place-items-center rounded-full bg-gold-100 text-3xl text-gold-500 dark:bg-gold-400/15 dark:text-gold-400">
                <Fa icon="screwdriver-wrench" />
            </span>
            <h2 class="text-xl font-semibold">{{ t('common.comingSoon') }}</h2>
            <p class="mt-2 max-w-md text-neutral-500 dark:text-neutral-400">{{ t('admin.placeholder') }}</p>
        </div>
    </Card>
</template>
