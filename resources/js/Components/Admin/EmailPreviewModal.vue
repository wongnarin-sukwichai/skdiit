<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

// One sent email as the recipient saw it (same look as resources/views/mail), from the email log
const props = defineProps({
    email: { type: Object, default: null },
});
const emit = defineEmits(['close', 'resend']);

const { t, tr, formatDate } = useI18n();
const site = useSite();

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
                <dd>{{ site.conference.shortName }} &lt;{{ site.conference.email }}&gt;</dd>
                <dt class="text-neutral-400">{{ t('admin.notificationsPage.to') }}</dt>
                <dd class="break-all">{{ tr(shown.name) }} &lt;{{ shown.to }}&gt;</dd>
                <dt class="text-neutral-400">{{ t('admin.notificationsPage.subject') }}</dt>
                <dd class="font-semibold">{{ shown.subject.th }} / {{ shown.subject.en }}</dd>
                <dt class="text-neutral-400">{{ t('admin.notificationsPage.sentAt') }}</dt>
                <dd>{{ formatDate(shown.at, { dateStyle: 'medium', timeStyle: 'short' }) }} · <span class="font-mono text-xs text-neutral-400">{{ shown.template }}</span></dd>
            </dl>

            <!-- Message (Thai first, then English, like the real template) -->
            <div class="max-h-[55vh] overflow-y-auto rounded-2xl border border-gold-400/30 bg-[#161616] p-6 text-sm leading-relaxed text-neutral-200">
                <p class="mb-1 text-xs tracking-[0.2em] text-gold-400">{{ site.conference.shortName }}</p>
                <h3 class="mb-3 text-lg font-semibold text-white">{{ shown.subject.th }}</h3>
                <p class="mb-2">เรียน {{ shown.name.th }}</p>
                <p v-for="(line, i) in shown.body.th" :key="`th-${i}`" class="mb-2">{{ line }}</p>
                <p class="my-5 text-center">
                    <span class="inline-block rounded-lg bg-gold-400 px-6 py-2.5 font-semibold text-black">{{ shown.button.th }} / {{ shown.button.en }}</span>
                </p>
                <hr class="my-5 border-white/10">
                <div class="text-neutral-400">
                    <p class="mb-2">Dear {{ shown.name.en }},</p>
                    <p v-for="(line, i) in shown.body.en" :key="`en-${i}`" class="mb-2">{{ line }}</p>
                </div>
            </div>

            <p v-if="shown.error" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">
                <Fa icon="circle-exclamation" class="mr-1" /> {{ t('admin.notificationsPage.notDelivered', { error: shown.error }) }}
            </p>

            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.close') }}</button>
                <button type="button" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="emit('resend', shown)">
                    <Fa icon="paper-plane" /> {{ t('admin.notificationsPage.resend') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
