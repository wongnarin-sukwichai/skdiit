<script setup>
import { useI18n } from '@/i18n';

// After a password was emailed: where it went, plus the password itself while testing without SMTP
defineProps({
    email: { type: String, required: true },
    devPassword: { type: String, default: null },
});

const { t } = useI18n();
</script>

<template>
    <div class="text-center">
        <span class="mx-auto mb-4 grid size-14 place-items-center rounded-full bg-gold-400/15 text-2xl text-gold-400">
            <Fa icon="envelope" />
        </span>
        <p class="text-sm leading-relaxed text-neutral-300">
            {{ t('auth.passwordSentTo') }}
            <span class="mt-1 block font-semibold break-all text-white">{{ email }}</span>
        </p>
        <p class="mt-3 text-xs text-neutral-400">{{ t('auth.passwordSentHint') }}</p>

        <!-- Testing phase only (config/registration.php: show_dev_password) -->
        <div v-if="devPassword" class="mt-5 rounded-lg border border-dashed border-gold-400/40 bg-gold-400/5 p-3">
            <p class="mb-1 flex items-center justify-center gap-2 text-xs font-semibold text-gold-400">
                <Fa icon="flask" /> {{ t('auth.devPassword') }}
            </p>
            <p class="font-mono text-2xl tracking-[0.3em] text-white">{{ devPassword }}</p>
        </div>
    </div>
</template>
