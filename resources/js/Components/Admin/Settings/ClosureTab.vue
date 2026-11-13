<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BilingualInput from '@/Components/Admin/Settings/BilingualInput.vue';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

// Settings › ปิดระบบ (2026-11-13): switch the whole system off when the conference is over; only admin gets in
const props = defineProps({
    closure: { type: Object, required: true }, // { closed, message: { th, en } }
});

const { t, tr } = useI18n();
const site = useSite();

const toForm = (c) => ({ closed: c.closed, message: { th: c.message.th, en: c.message.en } });
const form = useForm(toForm(props.closure));
watch(() => props.closure, (value) => {
    form.defaults(toForm(value));
    form.reset();
});

// Closing locks everyone out at once: confirm first; opening needs no confirmation
const confirming = ref(false);
function save() {
    if (form.closed && !props.closure.closed) {
        confirming.value = true;
        return;
    }
    send();
}
function send() {
    confirming.value = false;
    form.put('/admin/settings/closure', { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <form class="max-w-3xl space-y-6" @submit.prevent="save">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.closure.intro') }}</p>

        <label
            for="site-closed"
            :class="form.closed ? 'border-red-300 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10' : 'border-neutral-200 dark:border-white/10'"
            class="flex items-center gap-4 rounded-xl border p-4 transition"
        >
            <span class="relative inline-flex h-7 w-12 shrink-0">
                <input id="site-closed" v-model="form.closed" type="checkbox" class="peer sr-only">
                <span class="absolute inset-0 rounded-full bg-neutral-300 transition peer-checked:bg-red-500 peer-focus-visible:ring-2 peer-focus-visible:ring-gold-400 dark:bg-white/20" />
                <span class="absolute top-1 left-1 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5" />
            </span>
            <span class="min-w-0">
                <span class="block font-semibold"><Fa icon="power-off" class="mr-1.5" />{{ t('admin.closure.switch') }}</span>
                <span :class="form.closed ? 'text-red-700 dark:text-red-300' : 'text-neutral-500 dark:text-neutral-400'" class="block text-sm">
                    {{ t(form.closed ? 'admin.closure.stateClosed' : 'admin.closure.stateOpen') }}
                </span>
            </span>
        </label>

        <div>
            <BilingualInput v-model="form.message" :label="t('admin.closure.message')" field="message" :errors="form.errors" multiline :maxlength="2000" />
            <p class="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.closure.messageHint') }}</p>
        </div>

        <!-- What visitors will read -->
        <div class="space-y-2">
            <p class="text-sm font-medium">{{ t('admin.closure.preview') }}</p>
            <div class="dark rounded-xl border border-gold-400/25 bg-ink-950 p-6 text-center text-neutral-100">
                <p class="text-xs tracking-[0.3em] text-gold-400 uppercase">{{ site.conference.shortName }}</p>
                <p class="mt-2 text-lg font-semibold">{{ t('closed.title') }}</p>
                <p class="mx-auto mt-2 max-w-xl text-sm whitespace-pre-line text-neutral-300">{{ tr(form.message) || t('closed.defaultMessage', { name: site.conference.shortName }) }}</p>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing || !form.isDirty">
                <Fa icon="floppy-disk" /> {{ t('admin.closure.save') }}
            </button>
        </div>
    </form>

    <ConfirmModal
        :show="confirming"
        icon="power-off"
        danger
        :title="t('admin.closure.confirmTitle')"
        :message="t('admin.closure.confirmText')"
        :confirm-label="t('admin.closure.switch')"
        @close="confirming = false"
        @confirm="send"
    />
</template>
