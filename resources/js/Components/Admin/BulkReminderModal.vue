<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import MailPreview from '@/Components/Admin/MailPreview.vue';
import { useI18n } from '@/i18n';

/*
 * A reminder to a group of authors (2026-11-13: authors yet to answer a journal offer, presenters without slides):
 * how many will get it and the first one's email as it will go out, then send. previewUrl answers { count, preview }.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    intro: { type: String, required: true },
    previewUrl: { type: String, required: true },
    sendUrl: { type: String, required: true },
    confirmLabel: { type: String, required: true },
});
const emit = defineEmits(['close']);

const { t } = useI18n();
const data = ref(null); // { count, preview }
const failed = ref(false);
const busy = ref(false);

watch(() => props.show, async (open) => {
    if (!open) return;
    data.value = null;
    failed.value = false;
    try {
        const response = await fetch(props.previewUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(response.statusText);
        data.value = await response.json();
    } catch {
        failed.value = true;
    }
});

function send() {
    router.post(props.sendUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; emit('close'); },
    });
}
</script>

<template>
    <Modal :show="show" max-width="max-w-2xl" @close="emit('close')">
        <div class="space-y-4 p-6 sm:p-8">
            <h2 class="flex items-center gap-2 text-xl font-semibold"><Fa icon="envelope" class="text-gold-400" />{{ title }}</h2>
            <p class="text-sm text-neutral-400">{{ intro }}</p>
            <p v-if="failed" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ t('remindAuthor.previewFailed') }}</p>
            <p v-else-if="!data" class="py-8 text-center text-sm text-neutral-400"><Fa icon="spinner" spin class="mr-2" />{{ t('remindAuthor.loading') }}</p>
            <MailPreview v-else-if="data.preview" :preview="data.preview" :recipients="data.count" @leave="emit('close')" />
            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="button" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-50" :disabled="!data?.count || busy" @click="send">
                    <Fa icon="paper-plane" /> {{ confirmLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>
