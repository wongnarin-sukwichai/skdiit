<script setup>
import { computed, ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/i18n';
import { formatFileSize } from '@/utils/format';

/*
 * Every manuscript version, newest first (Submission::versionsFor): download any version and its response
 * letter, read how the author answered each reviewer, and open two PDF versions side by side.
 */
const props = defineProps({
    versions: { type: Array, required: true },
    showNames: { type: Boolean, default: false }, // staff see which reviewer an answer is for
});

const { t, formatDate } = useI18n();
const when = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const open = ref(new Set([props.versions[0]?.version]));
function toggle(version) {
    const next = new Set(open.value);
    next.has(version) ? next.delete(version) : next.add(version);
    open.value = next;
}

// Side by side: a version with the one before it, when both are PDF (Word cannot be shown in the browser)
const previousOf = (i) => props.versions[i + 1];
const canCompare = (i) => props.versions[i].file?.isPdf && previousOf(i)?.file?.isPdf;
const comparing = ref(null); // { left, right } versions
const shown = ref(null);
function compare(i) {
    comparing.value = { left: previousOf(i), right: props.versions[i] };
    shown.value = comparing.value;
}
const stageLabel = (v) => (v.stage === 'initial' ? t('versions.stage.initial') : v.stage === 'revision' ? t('versions.stage.revision', { round: v.round }) : t('versions.stage.edit'));
const hasAnswers = computed(() => props.versions.some((v) => v.responses.length || v.letter));

const btn = 'inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium whitespace-nowrap transition hover:border-gold-400 hover:text-gold-600 dark:border-white/15 dark:hover:text-gold-400';
</script>

<template>
    <section v-if="versions.length" class="@container rounded-xl border border-neutral-200 p-4 dark:border-white/10">
        <h2 class="mb-1 flex items-center gap-2 font-semibold">
            <Fa icon="layer-group" class="text-gold-400" /> {{ t('versions.title') }}
            <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-white/10 dark:text-neutral-300">{{ versions.length }}</span>
        </h2>
        <p class="mb-4 text-xs text-neutral-500 dark:text-neutral-400">{{ t(hasAnswers ? 'versions.hint' : 'versions.hintSimple') }}</p>

        <ol class="space-y-3">
            <li v-for="(v, i) in versions" :key="v.version" :class="i === 0 ? 'border-gold-300 dark:border-gold-400/40' : 'border-neutral-200 dark:border-white/10'" class="rounded-lg border">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 p-3">
                    <span :class="i === 0 ? 'bg-gold-400 text-black' : 'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-neutral-300'" class="grid size-9 shrink-0 place-items-center rounded-full text-sm font-bold">v{{ v.version }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">
                            {{ stageLabel(v) }}
                            <span v-if="i === 0" class="ml-1 rounded-full bg-gold-100 px-2 py-0.5 text-xs text-gold-700 dark:bg-gold-400/15 dark:text-gold-300">{{ t('versions.current') }}</span>
                        </p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ when(v.at) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <a :href="v.file.url" :class="btn" :title="v.file.name">
                            <Fa :icon="v.file.isPdf ? 'file-pdf' : 'file-word'" /> {{ t('versions.manuscript') }} · {{ formatFileSize(v.file.size) }}
                        </a>
                        <a v-for="file in v.word ?? []" :key="file.id" :href="file.url" :class="btn" :title="file.name"><Fa icon="file-word" /> {{ t('versions.word') }}</a>
                        <a v-for="file in v.turnitin ?? []" :key="file.id" :href="file.url" :class="btn" :title="file.name"><Fa icon="file-circle-check" /> {{ t('versions.turnitin') }}</a>
                        <a v-for="file in v.supplementary ?? []" :key="file.id" :href="file.url" :class="btn" :title="file.name"><Fa icon="paperclip" /> {{ file.name }}</a>
                        <a v-if="v.letter" :href="v.letter.url" :class="btn" :title="v.letter.name">
                            <Fa icon="paperclip" /> {{ t('versions.letter') }}
                        </a>
                        <button v-if="canCompare(i)" type="button" :class="[btn, 'border-gold-400 text-gold-700 dark:text-gold-300']" @click="compare(i)">
                            <Fa icon="table-columns" /> {{ t('versions.compare', { from: previousOf(i).version, to: v.version }) }}
                        </button>
                        <button v-if="v.responses.length" type="button" :class="btn" @click="toggle(v.version)">
                            <Fa :icon="open.has(v.version) ? 'chevron-up' : 'comment'" /> {{ t('versions.answers', { n: v.responses.length }) }}
                        </button>
                    </div>
                </div>

                <!-- How the author answered each reviewer (and the editor) -->
                <Transition name="fade">
                    <ul v-if="v.responses.length && open.has(v.version)" class="space-y-2 border-t border-neutral-200 p-3 dark:border-white/10">
                        <li v-for="(r, j) in v.responses" :key="j" class="grid gap-2 rounded-lg bg-neutral-50 p-3 text-sm @2xl:grid-cols-2 dark:bg-white/5">
                            <div>
                                <p class="mb-1 text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                                    {{ r.to === 'editor' ? t('versions.toEditor') : t('reviews.feedback.reviewer', { n: r.number }) }}
                                    <template v-if="showNames && r.reviewer"> · {{ r.reviewer }}</template>
                                </p>
                                <p v-if="r.comment" class="line-clamp-6 whitespace-pre-line text-neutral-600 dark:text-neutral-300" :title="r.comment">{{ r.comment }}</p>
                                <p v-else class="text-neutral-400 italic">{{ t('versions.noComment') }}</p>
                            </div>
                            <div class="border-t border-gold-300 pt-2 @2xl:border-t-0 @2xl:border-l @2xl:pt-0 @2xl:pl-3 dark:border-gold-400/30">
                                <p class="mb-1 text-xs font-semibold text-gold-700 dark:text-gold-300"><Fa icon="reply" class="mr-1" />{{ t('versions.answer') }}</p>
                                <p class="whitespace-pre-line">{{ r.body }}</p>
                            </div>
                        </li>
                    </ul>
                </Transition>
            </li>
        </ol>

        <!-- Two PDF versions next to each other (each scrolls on its own) -->
        <Modal :show="!!comparing" max-width="max-w-[96vw]" @close="comparing = null">
            <div v-if="shown" class="p-4 sm:p-5">
                <h3 class="mb-3 pr-10 font-semibold">{{ t('versions.compareTitle', { from: shown.left.version, to: shown.right.version }) }}</h3>
                <div class="grid h-[78vh] gap-3 md:grid-cols-2">
                    <figure v-for="(v, k) in [shown.left, shown.right]" :key="k" class="flex min-h-0 flex-col">
                        <figcaption class="mb-1.5 flex items-center justify-between text-xs text-neutral-400">
                            <span><b class="text-neutral-200">v{{ v.version }}</b> · {{ stageLabel(v) }} · {{ when(v.at) }}</span>
                            <a :href="v.file.url" class="text-gold-400 hover:underline"><Fa icon="download" /></a>
                        </figcaption>
                        <iframe :src="`${v.file.url}?inline=1`" :title="`v${v.version}`" class="min-h-0 w-full flex-1 rounded-lg border border-white/10 bg-white" />
                    </figure>
                </div>
                <p class="mt-2 text-xs text-neutral-500">{{ t('versions.compareHint') }}</p>
            </div>
        </Modal>
    </section>
</template>
