<script setup>
import { computed } from 'vue';
import PresentationSlot from '@/Components/Admin/PresentationSlot.vue';
import FileLink from '@/Components/Admin/FileLink.vue';
import FileUploadForm from '@/Components/Admin/FileUploadForm.vue';
import { useI18n } from '@/i18n';

/*
 * The author's side once a paper moves on: gone to a journal (the author accepted its offer: submit on its site),
 * or accepted in the conference track (camera-ready); then the presentation slot and the slides.
 */
const props = defineProps({
    submission: { type: Object, required: true },
    maxMb: { type: Number, required: true },
});

const { t, formatDate } = useI18n();
const status = computed(() => props.submission.status);
const publication = computed(() => props.submission.publication);
const visible = computed(() => ['journal_accepted', 'accepted', 'camera_ready'].includes(status.value));
const until = (iso) => formatDate(iso, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <section v-if="visible" class="space-y-4 rounded-xl border border-gold-300 p-4 dark:border-gold-400/30">
        <h2 class="flex items-center gap-2 font-semibold">
            <Fa :icon="status.startsWith('journal') ? 'newspaper' : 'book'" class="text-gold-400" />
            {{ t(status.startsWith('journal') ? 'publication.route.journal' : 'publication.title') }}
        </h2>

        <!-- Accepted a journal offer: continue on the journal's site (answering the offer happens on the journey card) -->
        <template v-if="status === 'journal_accepted'">
            <p class="text-sm text-emerald-700 dark:text-emerald-300">
                <Fa icon="circle-check" class="mr-1" /> {{ t('publication.journal.accepted', { journal: submission.journal?.name ?? '' }) }}
            </p>
            <a
                v-if="submission.journal?.url"
                :href="submission.journal.url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300"
            >
                <Fa icon="arrow-up-right-from-square" /> {{ t('publication.journal.goToJournal') }}
            </a>
        </template>

        <!-- Conference track: camera-ready file (replaceable) -->
        <template v-else>
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ t(publication.cameraReady ? 'publication.cameraReady.done' : 'publication.cameraReady.intro') }}</p>
            <FileLink v-if="publication.cameraReady" :file="publication.cameraReady" icon="file-circle-check" />
            <p v-if="submission.cameraReadyApproved" class="text-sm text-emerald-600 dark:text-emerald-400"><Fa icon="circle-check" class="mr-1" />{{ t('publication.check.authorApproved') }}</p>
            <p v-else-if="status === 'camera_ready'" class="text-sm text-sky-700 dark:text-sky-300"><Fa icon="magnifying-glass" class="mr-1" />{{ t('publication.check.authorChecking') }}</p>
            <FileUploadForm
                v-if="!submission.cameraReadyApproved"
                :url="`/admin/my-submissions/${submission.id}/camera-ready`"
                :types="['pdf', 'doc', 'docx']"
                :max-mb="maxMb"
                :placeholder="t(publication.cameraReady ? 'publication.cameraReady.replace' : 'submissions.form.dropFile')"
                :submit-label="t('publication.cameraReady.upload')"
            />
        </template>

        <!-- Presentation slot, then the slides (once the paper presents and has a slot) -->
        <PresentationSlot v-if="publication.presentation" :presentation="publication.presentation" />
        <div v-if="publication.presentation && status !== 'accepted'" class="space-y-2">
            <p class="flex items-center gap-2 text-sm font-medium"><Fa icon="file-powerpoint" class="text-gold-500" /> {{ t('publication.slides.title') }}</p>
            <FileLink v-if="publication.slides" :file="publication.slides" icon="file-powerpoint" />
            <template v-if="publication.canUploadSlides">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('publication.slides.until', { date: until(publication.slidesUntil) }) }}</p>
                <FileUploadForm
                    :url="`/admin/my-submissions/${submission.id}/slides`"
                    :types="['pdf', 'ppt', 'pptx']"
                    :max-mb="maxMb"
                    :placeholder="t(publication.slides ? 'publication.slides.replace' : 'publication.slides.drop')"
                    :submit-label="t('publication.slides.upload')"
                />
            </template>
            <p v-else class="text-sm text-neutral-500 dark:text-neutral-400"><Fa icon="lock" class="mr-1" /> {{ t('publication.slides.closed') }}</p>
        </div>
    </section>
</template>
