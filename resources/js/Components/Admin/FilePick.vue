<script setup>
import { computed, ref } from 'vue';
import { useI18n } from '@/i18n';
import { formatFileSize } from '@/utils/format';

/*
 * One file field of the paper form (drop or pick), checked for type and size here and again on the server.
 * v-model is the File, or an array of Files when multiple.
 */
const props = defineProps({
    modelValue: { type: [Object, Array], default: null },
    types: { type: Array, required: true }, // allowed extensions, e.g. ['pdf']
    maxMb: { type: Number, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    icon: { type: String, default: 'file-arrow-up' },
    required: { type: Boolean, default: false },
    multiple: { type: Boolean, default: false },
    current: { type: Object, default: null }, // file already on the paper { name, size, url }
    error: { type: String, default: null }, // server error key
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();
const localError = ref(null);
const dragging = ref(false);
const picker = ref(null);
const files = computed(() => (props.multiple ? props.modelValue ?? [] : props.modelValue ? [props.modelValue] : []));

function pick(list) {
    localError.value = null;
    const picked = [...(list ?? [])];
    for (const file of picked) {
        if (!props.types.includes(file.name.split('.').pop().toLowerCase())) return (localError.value = 'validation.file_type');
        if (file.size > props.maxMb * 1024 * 1024) return (localError.value = 'validation.file_size');
    }
    if (!picked.length) return;
    emit('update:modelValue', props.multiple ? [...files.value, ...picked] : picked[0]);
    if (picker.value) picker.value.value = '';
}
function remove(i) {
    if (!props.multiple) return emit('update:modelValue', null);
    emit('update:modelValue', files.value.filter((_, k) => k !== i));
}
const message = computed(() => {
    const key = localError.value ?? props.error;
    return key ? t(key, { max: props.maxMb }) : null;
});
</script>

<template>
    <div>
        <p class="mb-1.5 flex items-center gap-2 text-sm font-medium">
            <Fa :icon="icon" class="text-gold-500" /> {{ label }}
            <span v-if="required" class="text-red-500">*</span>
            <span v-else class="text-xs font-normal text-neutral-400">({{ t('submissions.form.optional') }})</span>
        </p>
        <label
            :class="[dragging ? 'border-gold-400 bg-gold-50 dark:bg-gold-400/10' : 'border-neutral-300 dark:border-white/15', message ? 'border-red-500/70' : '']"
            class="flex flex-col items-center rounded-xl border-2 border-dashed px-4 py-5 text-center transition hover:border-gold-400"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="dragging = false; pick($event.dataTransfer.files)"
        >
            <input ref="picker" type="file" :multiple="multiple" :accept="types.map((type) => `.${type}`).join(',')" class="sr-only" @change="pick($event.target.files)">
            <span class="text-sm font-medium">{{ t(multiple ? 'submissions.files.dropMany' : current ? 'submissions.form.replaceFile' : 'submissions.form.dropFile') }}</span>
            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.form.typesRule', { types: types.join(', ').toUpperCase(), max: maxMb }) }}</span>
        </label>
        <ul v-if="files.length" class="mt-2 space-y-1">
            <li v-for="(file, i) in files" :key="`${file.name}-${i}`" class="flex items-center gap-2 text-sm">
                <Fa icon="paperclip" class="text-gold-500" />
                <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatFileSize(file.size) }}</span>
                <button type="button" class="text-neutral-400 hover:text-red-500" :aria-label="t('common.cancel')" @click="remove(i)"><Fa icon="xmark" /></button>
            </li>
        </ul>
        <p v-else-if="current" class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
            {{ t('submissions.form.currentFile') }}
            <a :href="current.url" class="text-gold-600 hover:underline dark:text-gold-400">{{ current.name }}</a>
            ({{ formatFileSize(current.size) }})
        </p>
        <span v-if="message" class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ message }}</span>
        <p v-if="hint" class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ hint }}</p>
    </div>
</template>
