<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DeadlineNotice from '@/Components/Admin/DeadlineNotice.vue';
import RevisionNotice from '@/Components/Admin/RevisionNotice.vue';
import ReviewerFeedback from '@/Components/Admin/ReviewerFeedback.vue';
import FilePick from '@/Components/Admin/FilePick.vue';
import { useI18n } from '@/i18n';
import { useAffiliations } from '@/composables/useAffiliations';

const props = defineProps({
    submission: { type: Object, default: null }, // null = new paper
    tracks: { type: Array, required: true },
    maxMb: { type: Number, required: true },
    deadline: { type: String, default: null },
    isOpen: { type: Boolean, required: true },
    opensAt: { type: String, default: null },
    phase: { type: String, required: true }, // upcoming | open | closed (Submission::window())
});

const { t, tr } = useI18n();
const isEdit = computed(() => !!props.submission);
// Sent back by the editor: saving resubmits the paper for screening
const isRevision = computed(() => ['screening_revision', 'revision_minor', 'revision_major'].includes(props.submission?.status));

// A new paper starts with the signed-in user as the first author
const me = usePage().props.auth.user;
const { affiliationOf } = useAffiliations();
// uid keeps list animations attached to the right author; the server ignores it
let nextUid = 0;
const withUid = (author) => ({ ...author, uid: nextUid++ });
const blankAuthor = () => withUid({ name: '', affiliation: '', email: '' });
const form = useForm({
    track_id: props.submission?.track_id ?? '',
    title_th: props.submission?.title_th ?? '',
    title_en: props.submission?.title_en ?? '',
    abstract_th: props.submission?.abstract_th ?? '',
    abstract_en: props.submission?.abstract_en ?? '',
    keywords_th: props.submission?.keywords_th ?? '',
    keywords_en: props.submission?.keywords_en ?? '',
    authors: props.submission?.authors.map((a) => withUid({ ...a, email: a.email ?? '' }))
        ?? [withUid({ name: me.name, affiliation: affiliationOf(me), email: me.email })],
    // Files: the PDF ('file') and Word versions, the Turnitin report, and optional supplementary files
    file: null,
    word_file: null,
    turnitin_file: null,
    supplementary_files: [],
    // Revision answers (MySubmissionController::validatedAnswers): review id => text, a note to the editor, a letter
    responses: Object.fromEntries((props.submission?.reviewsToAnswer ?? []).map((r) => [r.id, ''])),
    response_editor: '',
    response_file: null,
});
const toAnswer = computed(() => props.submission?.reviewsToAnswer ?? []);
const letterPicker = ref(null);
function pickLetter(event) {
    form.response_file = event.target.files[0] ?? null;
}
const responseErr = (id) => form.errors[`responses.${id}`];

// Authors: add, remove, reorder (drag or arrows)
function addAuthor() {
    form.authors.push(blankAuthor());
}
function removeAuthor(i) {
    form.authors.splice(i, 1);
}
function move(from, to) {
    if (to < 0 || to >= form.authors.length) return;
    const [author] = form.authors.splice(from, 1);
    form.authors.splice(to, 0, author);
}
const dragFrom = ref(null);
const dragOver = ref(null);
function onDrop(i) {
    if (dragFrom.value !== null) move(dragFrom.value, i);
    dragFrom.value = dragOver.value = null;
}

// Required files: all three for a new paper; PDF and Word for a revision (the last Turnitin report stays); none when editing
const needs = computed(() => ({
    main: !isEdit.value || isRevision.value,
    turnitin: !isEdit.value,
}));
const current = computed(() => props.submission?.files ?? {});
// The first error among the supplementary files (supplementary_files.0, .1, ...)
const supplementaryError = computed(() => form.errors.supplementary_files
    ?? Object.entries(form.errors).find(([key]) => key.startsWith('supplementary_files.'))?.[1]);

function submit() {
    const options = { preserveScroll: true, forceFormData: true };
    if (isEdit.value) {
        // Files need multipart POST; Laravel reads _method as PUT
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/my-submissions/${props.submission.id}`, options);
    } else {
        form.post('/admin/my-submissions', options);
    }
}

const err = (key) => form.errors[key];
const authorErr = (i, field) => form.errors[`authors.${i}.${field}`];
const message = (key) => (key ? t(key, { max: props.maxMb }) : null);

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const label = 'mb-1.5 block text-sm font-medium';
const required = 'text-red-500';
const errorText = 'mt-1 block text-xs text-red-500 dark:text-red-400';
const sectionTitle = 'mb-4 flex items-center gap-2 text-lg font-semibold';
</script>

<template>
    <Head :title="isEdit ? submission.code : t('submissions.mine.new')" />

    <Card class="space-y-6">
        <PageHeader
            icon="file-arrow-up"
            :title="isEdit ? t('submissions.edit') : t('submissions.mine.new')"
            :subtitle="isEdit ? submission.code : t('submissions.form.subtitle')"
        />
        <template v-if="isRevision">
            <RevisionNotice :status="submission.status" :comment="submission.feedback" :due-date="submission.revisionDueDate" in-form />
            <ReviewerFeedback v-if="!toAnswer.length" :rounds="submission.reviewFeedback" />
        </template>
        <DeadlineNotice v-else :opens-at="opensAt" :deadline="deadline" :phase="phase" />

        <form class="space-y-10" novalidate @submit.prevent="submit">
            <!-- Revision: how each comment was addressed (or a response letter) -->
            <section v-if="isRevision" class="rounded-xl border border-gold-300 bg-gold-50/50 p-4 dark:border-gold-400/30 dark:bg-gold-400/5">
                <h2 :class="sectionTitle"><Fa icon="reply" class="text-gold-400" /> {{ t('versions.form.title') }}</h2>
                <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-300">{{ t(toAnswer.length ? 'versions.form.intro' : 'versions.form.introScreening') }}</p>

                <div v-for="review in toAnswer" :key="review.id" class="mb-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg bg-white p-3 text-sm dark:bg-ink-900">
                        <p class="mb-1 text-xs font-semibold text-neutral-500 dark:text-neutral-400">{{ t('reviews.feedback.reviewer', { n: review.number }) }} · {{ t(`reviews.recommendation.${review.recommendation}`) }}</p>
                        <p class="max-h-48 overflow-y-auto whitespace-pre-line text-neutral-700 dark:text-neutral-200">{{ review.comment }}</p>
                    </div>
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gold-700 dark:text-gold-300">{{ t('versions.form.answer', { n: review.number }) }} <span v-if="!form.response_file" :class="required">*</span></span>
                        <textarea v-model="form.responses[review.id]" rows="5" :placeholder="t('versions.form.answerPlaceholder')" :class="[input, border(responseErr(review.id))]" />
                        <span v-if="responseErr(review.id)" :class="errorText">{{ message(responseErr(review.id)) }}</span>
                    </label>
                </div>

                <label class="mb-4 block">
                    <span class="mb-1 block text-xs font-semibold text-neutral-600 dark:text-neutral-300">{{ t('versions.form.toEditor') }}</span>
                    <textarea v-model="form.response_editor" rows="2" :placeholder="t('versions.form.toEditorPlaceholder')" :class="[input, border(form.errors.response_editor)]" />
                </label>

                <div>
                    <p class="mb-1 text-xs font-semibold text-neutral-600 dark:text-neutral-300">{{ t('versions.form.letter') }}</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm transition hover:border-gold-400 dark:border-white/15" @click="letterPicker.click()">
                            <Fa icon="paperclip" /> {{ form.response_file ? t('reviews.form.changeFile') : t('reviews.form.chooseFile') }}
                        </button>
                        <span v-if="form.response_file" class="flex items-center gap-2 text-sm">
                            {{ form.response_file.name }}
                            <button type="button" class="text-neutral-400 hover:text-red-500" :aria-label="t('common.cancel')" @click="form.response_file = null; letterPicker.value = ''"><Fa icon="xmark" /></button>
                        </span>
                        <input ref="letterPicker" type="file" accept=".pdf,.doc,.docx" class="hidden" @change="pickLetter">
                    </div>
                    <span v-if="form.errors.response_file" :class="errorText">{{ message(form.errors.response_file) }}</span>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ t(toAnswer.length ? 'versions.form.letterHint' : 'versions.form.letterHintScreening') }}</p>
                </div>
            </section>

            <!-- Track -->
            <section>
                <h2 :class="sectionTitle"><Fa icon="layer-group" class="text-gold-400" /> {{ t('submissions.fields.track') }}</h2>
                <select v-model="form.track_id" :disabled="submission?.trackLocked" :class="[input, border(err('track_id')), 'disabled:opacity-60 sm:max-w-md']">
                    <option value="" disabled>{{ t('submissions.form.chooseTrack') }}</option>
                    <option v-for="track in tracks" :key="track.id" :value="track.id">{{ tr(track.name) }}</option>
                </select>
                <span v-if="err('track_id')" :class="errorText">{{ message(err('track_id')) }}</span>
                <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">{{ submission?.trackLocked ? t('submissions.form.trackLocked') : t('submissions.form.trackHint') }}</p>
            </section>

            <!-- Paper details in both languages -->
            <section>
                <h2 :class="sectionTitle"><Fa icon="file-lines" class="text-gold-400" /> {{ t('submissions.form.details') }}</h2>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div v-for="lang in ['th', 'en']" :key="lang" class="space-y-4 rounded-xl border border-neutral-200 p-4 dark:border-white/10">
                        <p class="text-xs font-semibold text-gold-600 dark:text-gold-400">{{ t(`lang.${lang}`) }}</p>
                        <label class="block">
                            <span :class="label">{{ t('submissions.fields.title') }} <span :class="required">*</span></span>
                            <input v-model="form[`title_${lang}`]" :class="[input, border(err(`title_${lang}`))]" maxlength="500">
                            <span v-if="err(`title_${lang}`)" :class="errorText">{{ message(err(`title_${lang}`)) }}</span>
                        </label>
                        <label class="block">
                            <span :class="label">{{ t('submissions.fields.abstract') }} <span :class="required">*</span></span>
                            <textarea v-model="form[`abstract_${lang}`]" rows="8" :class="[input, border(err(`abstract_${lang}`))]" maxlength="10000" />
                            <span v-if="err(`abstract_${lang}`)" :class="errorText">{{ message(err(`abstract_${lang}`)) }}</span>
                        </label>
                        <label class="block">
                            <span :class="label">{{ t('submissions.fields.keywords') }} <span :class="required">*</span></span>
                            <input v-model="form[`keywords_${lang}`]" :class="[input, border(err(`keywords_${lang}`))]" :placeholder="t(`submissions.form.keywordsPlaceholder.${lang}`)" maxlength="500">
                            <span v-if="err(`keywords_${lang}`)" :class="errorText">{{ message(err(`keywords_${lang}`)) }}</span>
                        </label>
                    </div>
                </div>
            </section>

            <!-- Authors in byline order -->
            <section>
                <h2 :class="sectionTitle"><Fa icon="users" class="text-gold-400" /> {{ t('submissions.fields.authors') }}</h2>
                <p class="-mt-2 mb-4 text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.form.authorsHint') }}</p>
                <span v-if="err('authors')" :class="[errorText, 'mb-3']">{{ message(err('authors')) }}</span>

                <TransitionGroup tag="ol" name="fade" class="space-y-3">
                    <li
                        v-for="(author, i) in form.authors"
                        :key="author.uid"
                        :class="dragOver === i ? 'border-gold-400' : 'border-neutral-200 dark:border-white/10'"
                        class="flex gap-3 rounded-xl border bg-white p-3 transition dark:bg-ink-900"
                        @dragover.prevent="dragOver = i"
                        @dragleave="dragOver = null"
                        @drop.prevent="onDrop(i)"
                    >
                        <div class="flex flex-col items-center gap-1 pt-1">
                            <span
                                draggable="true"
                                class="grid size-7 cursor-grab place-items-center rounded-full bg-gold-100 text-xs font-semibold text-gold-700 active:cursor-grabbing dark:bg-gold-400/15 dark:text-gold-300"
                                :title="t('submissions.form.dragHint')"
                                @dragstart="dragFrom = i"
                                @dragend="dragFrom = dragOver = null"
                            >{{ i + 1 }}</span>
                            <button type="button" class="text-xs text-neutral-400 hover:text-gold-500 disabled:opacity-30" :disabled="i === 0" :aria-label="t('submissions.form.moveUp')" @click="move(i, i - 1)"><Fa icon="chevron-up" /></button>
                            <button type="button" class="text-xs text-neutral-400 hover:text-gold-500 disabled:opacity-30" :disabled="i === form.authors.length - 1" :aria-label="t('submissions.form.moveDown')" @click="move(i, i + 1)"><Fa icon="chevron-down" /></button>
                        </div>
                        <div class="grid flex-1 gap-3 md:grid-cols-3">
                            <label class="block">
                                <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.fields.authorName') }} <span :class="required">*</span></span>
                                <input v-model="author.name" :class="[input, border(authorErr(i, 'name'))]">
                                <span v-if="authorErr(i, 'name')" :class="errorText">{{ message(authorErr(i, 'name')) }}</span>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('submissions.fields.affiliation') }} <span :class="required">*</span></span>
                                <input v-model="author.affiliation" :class="[input, border(authorErr(i, 'affiliation'))]">
                                <span v-if="authorErr(i, 'affiliation')" :class="errorText">{{ message(authorErr(i, 'affiliation')) }}</span>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('auth.email') }} <span class="text-neutral-400">({{ t('submissions.form.optional') }})</span></span>
                                <input v-model="author.email" type="email" :class="[input, border(authorErr(i, 'email'))]">
                                <span v-if="authorErr(i, 'email')" :class="errorText">{{ message(authorErr(i, 'email')) }}</span>
                            </label>
                        </div>
                        <button
                            type="button"
                            class="self-start rounded-md p-2 text-neutral-400 transition hover:bg-red-50 hover:text-red-500 disabled:opacity-30 disabled:hover:bg-transparent dark:hover:bg-red-500/10"
                            :disabled="form.authors.length === 1"
                            :aria-label="t('submissions.form.removeAuthor')"
                            :title="t('submissions.form.removeAuthor')"
                            @click="removeAuthor(i)"
                        >
                            <Fa icon="trash-can" />
                        </button>
                    </li>
                </TransitionGroup>

                <button type="button" class="mt-3 flex items-center gap-2 rounded-lg border border-dashed border-gold-400 px-4 py-2.5 text-sm text-gold-600 transition hover:bg-gold-50 dark:text-gold-400 dark:hover:bg-gold-400/10" @click="addAuthor">
                    <Fa icon="user-plus" /> {{ t('submissions.form.addAuthor') }}
                </button>
            </section>

            <!-- Files -->
            <section>
                <h2 :class="sectionTitle"><Fa icon="file-arrow-up" class="text-gold-400" /> {{ t('submissions.files.title') }}</h2>
                <p class="-mt-2 mb-4 text-xs text-neutral-500 dark:text-neutral-400">{{ t(isRevision ? 'submissions.files.introRevision' : isEdit ? 'submissions.files.introEdit' : 'submissions.files.intro') }}</p>
                <div class="grid gap-6 md:grid-cols-2">
                    <FilePick v-model="form.file" :types="['pdf']" :max-mb="maxMb" icon="file-pdf" :label="t('submissions.files.pdf')" :hint="t('submissions.files.pdfHint')" :required="needs.main" :current="current.pdf" :error="err('file')" />
                    <FilePick v-model="form.word_file" :types="['doc', 'docx']" :max-mb="maxMb" icon="file-word" :label="t('submissions.files.word')" :hint="t('submissions.files.wordHint')" :required="needs.main" :current="current.word" :error="err('word_file')" />
                    <FilePick v-model="form.turnitin_file" :types="['pdf']" :max-mb="maxMb" icon="file-circle-check" :label="t('submissions.files.turnitin')" :hint="t(isRevision ? 'submissions.files.turnitinKeep' : 'submissions.files.turnitinHint')" :required="needs.turnitin" :current="current.turnitin" :error="err('turnitin_file')" />
                    <div>
                        <FilePick v-model="form.supplementary_files" multiple :types="['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip']" :max-mb="maxMb" icon="paperclip" :label="t('submissions.files.supplementary')" :hint="t('submissions.files.supplementaryHint')" :error="supplementaryError" />
                        <ul v-if="current.supplementary?.length" class="mt-2 space-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                            <li v-for="file in current.supplementary" :key="file.id"><Fa icon="paperclip" class="mr-1" /><a :href="file.url" class="text-gold-600 hover:underline dark:text-gold-400">{{ file.name }}</a></li>
                        </ul>
                    </div>
                </div>
                <div v-if="form.progress" class="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-white/10">
                    <div class="h-full bg-gold-400 transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3 border-t border-neutral-200 pt-6 dark:border-white/10">
                <button
                    type="submit"
                    class="flex items-center gap-2 rounded-lg bg-gold-400 px-6 py-2.5 font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <Fa :icon="isEdit && !isRevision ? 'floppy-disk' : 'paper-plane'" /> {{ isRevision ? t('submissions.revision.submit') : isEdit ? t('common.save') : t('submissions.form.submit') }}
                </button>
                <Link
                    :href="isEdit ? `/admin/my-submissions/${submission.id}` : '/admin/my-submissions'"
                    class="rounded-lg border border-neutral-300 px-5 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20"
                >
                    {{ t('common.cancel') }}
                </Link>
                <p v-if="Object.keys(form.errors).length" class="text-sm text-red-500 dark:text-red-400">
                    <Fa icon="circle-exclamation" /> {{ t('submissions.form.hasErrors') }}
                </p>
            </div>
        </form>
    </Card>
</template>
