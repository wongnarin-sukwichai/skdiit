<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

// One file to upload (drop or pick), checked for type and size before it is sent
const props = defineProps({
    url: { type: String, required: true },
    types: { type: Array, required: true }, // allowed extensions, e.g. ['pdf', 'docx']
    maxMb: { type: Number, required: true },
    placeholder: { type: String, required: true }, // text in the drop zone before a file is picked
    submitLabel: { type: String, required: true },
});

const { t } = useI18n();

const form = useForm({ file: null });
const error = ref(null);
const dragging = ref(false);
function pick(file) {
    error.value = null;
    if (!file) return;
    if (!props.types.includes(file.name.split('.').pop().toLowerCase())) return (error.value = 'validation.file_type');
    if (file.size > props.maxMb * 1024 * 1024) return (error.value = 'validation.file_size');
    form.file = file;
}
function upload() {
    form.post(props.url, { preserveScroll: true, forceFormData: true, onSuccess: () => form.reset() });
}
const message = computed(() => {
    const key = error.value ?? form.errors.file;
    return key ? t(key, { max: props.maxMb }) : null;
});
</script>

<template>
    <form class="space-y-2" @submit.prevent="upload">
        <label
            :class="[dragging ? 'border-gold-400 bg-gold-50 dark:bg-gold-400/10' : 'border-neutral-300 dark:border-white/15', message ? 'border-red-500/70' : '']"
            class="flex flex-col items-center rounded-xl border-2 border-dashed px-6 py-6 text-center transition hover:border-gold-400"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="dragging = false; pick($event.dataTransfer.files[0])"
        >
            <input type="file" :accept="types.map((type) => `.${type}`).join(',')" class="sr-only" @change="pick($event.target.files[0])">
            <Fa icon="file-arrow-up" class="mb-2 text-2xl text-gold-400" />
            <span class="text-sm font-medium">{{ form.file ? form.file.name : placeholder }}</span>
            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.form.typesRule', { types: types.join(', ').toUpperCase(), max: maxMb }) }}</span>
        </label>
        <span v-if="message" class="block text-xs text-red-500 dark:text-red-400">{{ message }}</span>
        <div v-if="form.progress" class="h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-white/10">
            <div class="h-full bg-gold-400 transition-all" :style="{ width: `${form.progress.percentage}%` }" />
        </div>
        <button
            type="submit"
            class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
            :disabled="!form.file || form.processing"
        >
            <Fa icon="paper-plane" /> {{ submitLabel }}
        </button>
    </form>
</template>
