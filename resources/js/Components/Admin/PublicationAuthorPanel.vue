<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import PresentationSlot from '@/Components/Admin/PresentationSlot.vue';
import { useI18n } from '@/i18n';
import { formatFileSize } from '@/utils/format';

// The author's side after acceptance: choose Option A/B, upload the camera-ready file, see the presentation slot
const props = defineProps({
    submission: { type: Object, required: true },
    maxMb: { type: Number, required: true },
});

const { t, formatDate } = useI18n();
const status = computed(() => props.submission.status);
const publication = computed(() => props.submission.publication);
const visible = computed(() => ['accepted', 'option_a', 'camera_ready', 'option_b', 'journal_accepted'].includes(status.value));

// Choose A or B (final)
const optionForm = useForm({ option: '' });
const confirmOpen = ref(false);
function chooseOption() {
    optionForm.post(`/admin/my-submissions/${props.submission.id}/option`, {
        preserveScroll: true,
        onFinish: () => (confirmOpen.value = false),
    });
}

// Camera-ready upload
const fileForm = useForm({ file: null });
const fileError = ref(null);
const dragging = ref(false);
function pickFile(file) {
    fileError.value = null;
    if (!file) return;
    if (!/\.(pdf|docx?)$/i.test(file.name)) return (fileError.value = 'validation.file_type');
    if (file.size > props.maxMb * 1024 * 1024) return (fileError.value = 'validation.file_size');
    fileForm.file = file;
}
function upload() {
    fileForm.post(`/admin/my-submissions/${props.submission.id}/camera-ready`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => fileForm.reset(),
    });
}
const fileMessage = computed(() => {
    const key = fileError.value ?? fileForm.errors.file;
    return key ? t(key, { max: props.maxMb }) : null;
});

const options = [
    { value: 'a', icon: 'book' },
    { value: 'b', icon: 'newspaper' },
];
</script>

<template>
    <section v-if="visible" class="rounded-xl border border-gold-300 p-4 dark:border-gold-400/30">
        <h2 class="mb-4 flex items-center gap-2 font-semibold"><Fa icon="book" class="text-gold-400" /> {{ t('publication.title') }}</h2>

        <!-- 1. Choose A or B -->
        <form v-if="status === 'accepted'" class="space-y-4" @submit.prevent="confirmOpen = true">
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ t('publication.chooseIntro') }}</p>
            <div class="grid gap-3 md:grid-cols-2" role="radiogroup">
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="optionForm.option === option.value"
                    :class="optionForm.option === option.value ? 'border-gold-400 bg-gold-50 dark:bg-gold-400/10' : 'border-neutral-300 hover:border-gold-400 dark:border-white/15'"
                    class="flex gap-3 rounded-xl border p-4 text-left transition"
                    @click="optionForm.option = option.value"
                >
                    <Fa :icon="option.icon" class="mt-1 text-xl text-gold-500" />
                    <span>
                        <span class="block font-semibold">{{ t(`publication.option.${option.value}.title`) }}</span>
                        <span class="mt-1 block text-sm text-neutral-600 dark:text-neutral-300">{{ t(`publication.option.${option.value}.description`) }}</span>
                    </span>
                </button>
            </div>
            <button
                type="submit"
                class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                :disabled="!optionForm.option || optionForm.processing"
            >
                <Fa icon="check" /> {{ t('publication.confirmOption') }}
            </button>
        </form>

        <div v-else class="space-y-4">
            <p class="flex items-center gap-2 text-sm">
                <Fa :icon="publication.option === 'a' ? 'book' : 'newspaper'" class="text-gold-500" />
                <span class="font-medium">{{ t(`publication.option.${publication.option}.title`) }}</span>
            </p>

            <!-- 2a. Option A: camera-ready file -->
            <template v-if="['option_a', 'camera_ready'].includes(status)">
                <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ t(publication.cameraReady ? 'publication.cameraReady.done' : 'publication.cameraReady.intro') }}</p>
                <a v-if="publication.cameraReady" :href="publication.cameraReady.url" class="flex items-center gap-3 rounded-lg border border-neutral-200 p-3 text-sm transition hover:border-gold-400 dark:border-white/10">
                    <Fa icon="file-circle-check" class="text-2xl text-emerald-500" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ publication.cameraReady.name }}</span>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ formatFileSize(publication.cameraReady.size) }} · {{ formatDate(publication.cameraReady.at, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                        </span>
                    </span>
                    <Fa icon="download" class="text-neutral-400" />
                </a>
                <form class="space-y-2" @submit.prevent="upload">
                    <label
                        :class="[dragging ? 'border-gold-400 bg-gold-50 dark:bg-gold-400/10' : 'border-neutral-300 dark:border-white/15', fileMessage ? 'border-red-500/70' : '']"
                        class="flex flex-col items-center rounded-xl border-2 border-dashed px-6 py-6 text-center transition hover:border-gold-400"
                        @dragover.prevent="dragging = true"
                        @dragleave="dragging = false"
                        @drop.prevent="dragging = false; pickFile($event.dataTransfer.files[0])"
                    >
                        <input type="file" accept=".pdf,.doc,.docx" class="sr-only" @change="pickFile($event.target.files[0])">
                        <Fa icon="file-arrow-up" class="mb-2 text-2xl text-gold-400" />
                        <span class="text-sm font-medium">{{ fileForm.file ? fileForm.file.name : t(publication.cameraReady ? 'publication.cameraReady.replace' : 'submissions.form.dropFile') }}</span>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.form.fileRules', { max: maxMb }) }}</span>
                    </label>
                    <span v-if="fileMessage" class="block text-xs text-red-500 dark:text-red-400">{{ fileMessage }}</span>
                    <div v-if="fileForm.progress" class="h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-white/10">
                        <div class="h-full bg-gold-400 transition-all" :style="{ width: `${fileForm.progress.percentage}%` }" />
                    </div>
                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                        :disabled="!fileForm.file || fileForm.processing"
                    >
                        <Fa icon="paper-plane" /> {{ t('publication.cameraReady.upload') }}
                    </button>
                </form>
            </template>

            <!-- 2b. Option B: the editors coordinate with the journal -->
            <p v-else-if="status === 'option_b'" class="text-sm text-neutral-600 dark:text-neutral-300">{{ t('publication.journal.waiting') }}</p>
            <p v-else-if="status === 'journal_accepted'" class="text-sm text-emerald-700 dark:text-emerald-300">
                <Fa icon="circle-check" class="mr-1" /> {{ t('publication.journal.accepted', { journal: publication.journal }) }}
            </p>

            <PresentationSlot v-if="publication.presentation" :presentation="publication.presentation" />
        </div>

        <Modal :show="confirmOpen" @close="confirmOpen = false">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                    <Fa icon="book" />
                </span>
                <h2 class="text-xl font-semibold">{{ t('publication.confirmTitle') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">
                    {{ t('publication.confirmText', { option: optionForm.option ? t(`publication.option.${optionForm.option}.title`) : '' }) }}
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="confirmOpen = false">{{ t('common.cancel') }}</button>
                    <button class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="optionForm.processing" @click="chooseOption">
                        <Fa icon="check" /> {{ t('submissions.screening.confirm') }}
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
