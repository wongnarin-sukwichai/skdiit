<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';

/**
 * A password admin generated to pass on by private chat. It is shown once: after closing,
 * nobody can see it again (the server keeps only its hash).
 * result: { name, email, password }
 */
const props = defineProps({
    result: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const { t } = useI18n();

// Keep the last result so the content stays visible while the modal fades out
const shown = ref(null);
const visible = ref(false);
const copied = ref(false);
watch(() => props.result, (value) => {
    if (!value) return;
    shown.value = value;
    visible.value = false;
    copied.value = false;
}, { immediate: true });

async function copy() {
    try {
        await navigator.clipboard.writeText(shown.value.password);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        visible.value = true; // clipboard blocked (e.g. plain http): show it so it can be copied by hand
    }
}

const btn = 'grid size-11 place-items-center rounded-lg border border-white/15 text-neutral-200 transition hover:border-gold-400 hover:text-gold-400';
</script>

<template>
    <Modal :show="!!result" @close="emit('close')">
        <div v-if="shown" class="p-6 sm:p-8">
            <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                <Fa icon="key" />
            </span>
            <h2 class="mb-1 text-xl font-semibold">{{ t('admin.oneTimePassword.title') }}</h2>
            <p class="mb-5 text-sm break-all text-neutral-400">{{ shown.name }} · {{ shown.email }}</p>

            <div class="flex items-center gap-2">
                <code class="flex-1 rounded-lg border border-white/10 bg-ink-800 px-4 py-2 text-center font-mono text-2xl tracking-[0.4em]">
                    {{ visible ? shown.password : '******' }}
                </code>
                <button
                    type="button"
                    :class="btn"
                    :title="visible ? t('admin.oneTimePassword.hide') : t('admin.oneTimePassword.show')"
                    :aria-label="visible ? t('admin.oneTimePassword.hide') : t('admin.oneTimePassword.show')"
                    @click="visible = !visible"
                >
                    <Fa :icon="visible ? 'eye-slash' : 'eye'" />
                </button>
                <button type="button" :class="btn" :title="copied ? t('admin.oneTimePassword.copied') : t('admin.oneTimePassword.copy')" :aria-label="t('admin.oneTimePassword.copy')" @click="copy">
                    <Fa :icon="copied ? 'check' : 'copy'" :class="copied && 'text-emerald-400'" />
                </button>
            </div>

            <ul class="mt-5 space-y-1.5 rounded-lg border border-gold-400/30 bg-gold-400/5 px-4 py-3 text-xs leading-relaxed text-neutral-300">
                <li><Fa icon="triangle-exclamation" class="mr-1.5 text-gold-400" /> {{ t('admin.oneTimePassword.once') }}</li>
                <li><Fa icon="comment" class="mr-1.5 text-gold-400" /> {{ t('admin.oneTimePassword.chat') }}</li>
                <li><Fa icon="envelope" class="mr-1.5 text-gold-400" /> {{ t('admin.oneTimePassword.emailNote') }}</li>
            </ul>

            <div class="mt-6 flex justify-end">
                <button type="button" class="rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="emit('close')">
                    {{ t('common.close') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
