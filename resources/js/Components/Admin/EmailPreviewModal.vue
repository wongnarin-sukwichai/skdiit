<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

/*
 * One sent email as the recipient saw it (same look as resources/views/mail/templated), from the email log
 * (EmailLog::toClient). Password emails keep no text: the password is never stored.
 */
const props = defineProps({
    email: { type: Object, default: null },
    kindLabel: { type: Function, required: true },
});
const emit = defineEmits(['close', 'resend']);

const { t, formatDate } = useI18n();
const site = useSite();
const readOnly = !!usePage().props.impersonation;

// Keep the last email so the content stays visible while the modal fades out
const shown = ref(null);
watch(() => props.email, (value) => value && (shown.value = value), { immediate: true });
</script>

<template>
    <Modal :show="!!email" max-width="max-w-2xl" @close="emit('close')">
        <div v-if="shown" class="space-y-4 p-6 sm:p-8">
            <!-- Envelope -->
            <dl class="grid grid-cols-[5rem_1fr] gap-x-3 gap-y-1 border-b border-white/10 pb-4 text-sm">
                <dt class="text-neutral-400">{{ t('admin.notificationsPage.from') }}</dt>
                <dd>{{ site.conference.shortName }}</dd>
                <dt class="text-neutral-400">{{ t('admin.notificationsPage.to') }}</dt>
                <dd class="break-all">{{ shown.name }} &lt;{{ shown.to }}&gt;</dd>
                <dt class="text-neutral-400">{{ t('admin.notificationsPage.subject') }}</dt>
                <dd class="font-semibold">{{ shown.subject }}</dd>
                <dt class="text-neutral-400">{{ t('admin.notificationsPage.sentAt') }}</dt>
                <dd>{{ formatDate(shown.at, { dateStyle: 'medium', timeStyle: 'short' }) }} · <span class="text-xs text-neutral-400">{{ kindLabel(shown.kind) }}</span></dd>
            </dl>

            <!-- Message (Thai first, then English, like the real template) -->
            <p v-if="shown.password" class="rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-neutral-300">
                <Fa icon="lock" class="mr-1 text-gold-400" /> {{ t('admin.notificationsPage.passwordHidden') }}
            </p>
            <div v-else class="max-h-[55vh] overflow-y-auto rounded-2xl border border-gold-400/30 bg-[#161616] p-6 text-sm leading-relaxed whitespace-pre-line text-neutral-200">
                <p class="mb-4 text-xs tracking-[0.2em] text-gold-400">{{ site.conference.shortName }}</p>
                <p>{{ shown.bodyTh }}</p>
                <p v-if="shown.button" class="my-5 text-center">
                    <span class="inline-block rounded-lg bg-gold-400 px-6 py-2.5 font-semibold text-black">เข้าสู่ระบบ / Open the system</span>
                </p>
                <template v-if="shown.bodyEn">
                    <hr class="my-5 border-white/10">
                    <p class="text-neutral-400">{{ shown.bodyEn }}</p>
                </template>
            </div>

            <p v-if="shown.error" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm break-all text-red-300">
                <Fa icon="circle-exclamation" class="mr-1" /> {{ t('admin.notificationsPage.notDelivered', { error: shown.error }) }}
            </p>

            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.close') }}</button>
                <button v-if="!readOnly" type="button" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="emit('resend', shown)">
                    <Fa icon="paper-plane" /> {{ t('admin.notificationsPage.resend') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
