<script setup>
import { useI18n } from '@/i18n';

// Read-only presentation date/time and room
defineProps({
    presentation: { type: Object, required: true }, // { at, room }
});

const { t, formatDate } = useI18n();
</script>

<template>
    <div class="flex items-start gap-3 rounded-lg bg-gold-50 px-4 py-3 text-sm dark:bg-gold-400/10">
        <Fa icon="person-chalkboard" class="mt-0.5 text-gold-600 dark:text-gold-400" />
        <div>
            <p class="font-medium">{{ t('publication.presentation.title') }}</p>
            <p class="text-neutral-700 dark:text-neutral-200">
                {{ formatDate(presentation.at, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
                · {{ formatDate(presentation.at, { hour: '2-digit', minute: '2-digit' }) }}
                · {{ t('publication.presentation.room') }} {{ presentation.room }}
            </p>
        </div>
    </div>
</template>
