<script setup>
import { useI18n } from '@/i18n';
import { formatFileSize } from '@/utils/format';

// A downloadable file row: name, size and upload time
defineProps({
    file: { type: Object, required: true }, // { name, size, at, url }
    icon: { type: String, default: 'file-lines' },
});

const { formatDate } = useI18n();
</script>

<template>
    <a :href="file.url" class="flex items-center gap-3 rounded-lg border border-neutral-200 p-3 text-sm transition hover:border-gold-400 dark:border-white/10">
        <Fa :icon="icon" class="text-2xl text-emerald-500" />
        <span class="min-w-0 flex-1">
            <span class="block truncate font-medium">{{ file.name }}</span>
            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                {{ formatFileSize(file.size) }} · {{ formatDate(file.at, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
            </span>
        </span>
        <Fa icon="download" class="text-neutral-400" />
    </a>
</template>
