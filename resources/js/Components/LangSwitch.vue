<script setup>
import { useI18n } from '@/i18n';

const { locale, setLocale, t } = useI18n();
</script>

<template>
    <div
        class="inline-flex rounded-lg border border-neutral-300 p-0.5 text-xs font-semibold dark:border-white/15"
        role="group"
        :aria-label="t('lang.switch')"
    >
        <button
            v-for="code in ['en', 'th']"
            :key="code"
            :class="locale === code ? 'bg-gold-400 text-black' : 'text-neutral-500 hover:text-gold-500 dark:text-neutral-300'"
            class="rounded-md px-2.5 py-1 uppercase transition"
            :title="t(`lang.${code}`)"
            @click="setLocale(code)"
        >
            {{ code }}
        </button>
    </div>
</template>
