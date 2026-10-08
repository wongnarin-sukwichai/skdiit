<script setup>
import { useI18n } from '@/i18n';

defineProps({
    role: { type: String, required: true }, // User::accessRole()
});

const { t } = useI18n();

const styles = {
    admin: 'bg-gold-100 text-gold-700 dark:bg-gold-400/15 dark:text-gold-300',
    editor: 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300',
    reviewer: 'bg-violet-100 text-violet-700 dark:bg-violet-400/15 dark:text-violet-300',
    finance: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300',
    author: 'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-300',
    attendee: 'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-neutral-300',
};
</script>

<template>
    <span :class="styles[role]" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap">
        {{ t(`admin.role.${role}`) }}
    </span>
</template>
