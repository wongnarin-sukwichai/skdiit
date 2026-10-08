<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import ReviewBadge from '@/Components/Admin/ReviewBadge.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    submissionId: { type: Number, required: true },
    round: { type: Number, required: true },
    reviews: { type: Array, required: true }, // current round, Review::toClient(withReviewer: true)
    options: { type: Array, required: true }, // reviewers of the paper's track not yet assigned, with conflict flag
    canAssign: { type: Boolean, required: true },
});

const { t, formatDate } = useI18n();
const day = (value) => formatDate(value, { day: 'numeric', month: 'short', year: 'numeric' });

// Local date as YYYY-MM-DD (toISOString would shift to UTC)
const isoDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const today = isoDate(new Date());
const inTwoWeeks = isoDate(new Date(Date.now() + 14 * 86400000));

// Assign
const form = useForm({ reviewer_id: '', due_date: inTwoWeeks });
const selected = computed(() => props.options.find((o) => o.id === form.reviewer_id));
function assign() {
    form.post(`/admin/submissions/${props.submissionId}/reviews`, {
        preserveScroll: true,
        onSuccess: () => form.reset('reviewer_id'),
    });
}

// Change due date
const editingId = ref(null);
const dueForm = useForm({ due_date: '' });
function editDue(review) {
    editingId.value = review.id;
    dueForm.clearErrors();
    dueForm.due_date = review.dueDate;
}
function saveDue(review) {
    dueForm.put(`/admin/submissions/${props.submissionId}/reviews/${review.id}`, {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });
}

// Remove (e.g. the reviewer declined by email)
const removing = ref(null);
const removeBusy = ref(false);
function remove() {
    router.delete(`/admin/submissions/${props.submissionId}/reviews/${removing.value.id}`, {
        preserveScroll: true,
        onStart: () => (removeBusy.value = true),
        onFinish: () => {
            removeBusy.value = false;
            removing.value = null;
        },
    });
}

const expanded = ref(new Set());
function toggle(id) {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

const input = 'rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800 dark:[color-scheme:dark]';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <section class="rounded-xl border border-neutral-200 p-4 dark:border-white/10">
        <h2 class="mb-4 flex flex-wrap items-center gap-2 font-semibold">
            <Fa icon="user-check" class="text-gold-400" /> {{ t('reviews.panel.title') }}
            <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-white/10 dark:text-neutral-300">{{ t('reviews.round', { round }) }}</span>
        </h2>

        <!-- Assigned reviewers -->
        <p v-if="!reviews.length" class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">{{ t('reviews.panel.none') }}</p>
        <ul v-else class="mb-5 divide-y divide-neutral-200 rounded-lg border border-neutral-200 dark:divide-white/5 dark:border-white/10">
            <li v-for="review in reviews" :key="review.id" class="p-3">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ review.reviewer.name }}</p>
                        <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ review.reviewer.email }}</p>
                    </div>

                    <!-- Due date, editable until the review is in -->
                    <form v-if="editingId === review.id" class="flex flex-wrap items-center gap-2" @submit.prevent="saveDue(review)">
                        <input v-model="dueForm.due_date" type="date" :min="today" :class="[input, border(dueForm.errors.due_date), 'py-1.5']" required>
                        <button type="submit" :class="iconBtn" :title="t('common.save')" :disabled="dueForm.processing"><Fa icon="check" class="text-xs" /></button>
                        <button type="button" :class="iconBtn" :title="t('common.cancel')" @click="editingId = null"><Fa icon="xmark" class="text-xs" /></button>
                        <span v-if="dueForm.errors.due_date" class="w-full text-xs text-red-500 dark:text-red-400">{{ t(dueForm.errors.due_date) }}</span>
                    </form>
                    <span v-else class="text-sm text-neutral-500 dark:text-neutral-400">
                        <Fa icon="calendar-days" class="mr-1" /> {{ t('reviews.due') }} {{ day(review.dueDate) }}
                    </span>

                    <ReviewBadge kind="state" :value="review.state" />
                    <ReviewBadge v-if="review.recommendation" kind="recommendation" :value="review.recommendation" />

                    <div class="flex gap-1.5">
                        <button v-if="review.comment" :class="iconBtn" :title="t('reviews.panel.showComment')" @click="toggle(review.id)">
                            <Fa :icon="expanded.has(review.id) ? 'chevron-up' : 'comment'" class="text-xs" />
                        </button>
                        <template v-if="canAssign && review.state !== 'submitted' && editingId !== review.id">
                            <button :class="iconBtn" :title="t('reviews.panel.changeDue')" @click="editDue(review)"><Fa icon="calendar-days" class="text-xs" /></button>
                            <button :class="iconBtn" :title="t('reviews.panel.remove')" @click="removing = review"><Fa icon="user-xmark" class="text-xs" /></button>
                        </template>
                    </div>
                </div>
                <Transition name="fade">
                    <blockquote v-if="expanded.has(review.id)" class="mt-3 rounded-lg bg-neutral-50 px-3 py-2 text-sm whitespace-pre-line text-neutral-700 dark:bg-white/5 dark:text-neutral-200">
                        {{ review.comment }}
                        <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">{{ t('reviews.submittedOn') }} {{ formatDate(review.submittedAt, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                    </blockquote>
                </Transition>
            </li>
        </ul>

        <!-- Assign a reviewer from the paper's track -->
        <form v-if="canAssign" class="space-y-2" @submit.prevent="assign">
            <p class="text-sm font-medium">{{ t('reviews.panel.assign') }}</p>
            <div class="grid gap-2 sm:grid-cols-[1fr_11rem_auto]">
                <select v-model="form.reviewer_id" :class="[input, border(form.errors.reviewer_id)]" :disabled="!options.length">
                    <option value="" disabled>{{ options.length ? t('reviews.panel.choose') : t('reviews.panel.noOptions') }}</option>
                    <option v-for="option in options" :key="option.id" :value="option.id">
                        {{ option.conflict ? '⚠ ' : '' }}{{ option.name }} ({{ option.email }})
                    </option>
                </select>
                <input v-model="form.due_date" type="date" :min="today" :class="[input, border(form.errors.due_date)]" :aria-label="t('reviews.due')" required>
                <button
                    type="submit"
                    class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                    :disabled="!form.reviewer_id || form.processing"
                >
                    <Fa icon="user-plus" /> {{ t('reviews.panel.assignButton') }}
                </button>
            </div>
            <span v-if="form.errors.reviewer_id" class="block text-xs text-red-500 dark:text-red-400">{{ t(form.errors.reviewer_id) }}</span>
            <span v-if="form.errors.due_date" class="block text-xs text-red-500 dark:text-red-400">{{ t(form.errors.due_date) }}</span>
            <Transition name="fade">
                <p v-if="selected?.conflict" class="flex items-start gap-2 rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-xs text-orange-800 dark:border-orange-400/30 dark:bg-orange-400/10 dark:text-orange-200">
                    <Fa icon="triangle-exclamation" class="mt-0.5" /> {{ t('reviews.panel.conflict') }}
                </p>
            </Transition>
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('reviews.panel.hint') }}</p>
        </form>
        <p v-else-if="!reviews.length" class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('reviews.panel.notYet') }}</p>

        <Modal :show="!!removing" @close="removing = null">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-red-500/15 text-lg text-red-400">
                    <Fa icon="user-xmark" />
                </span>
                <h2 class="text-xl font-semibold">{{ t('reviews.panel.removeTitle') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ t('reviews.panel.removeText', { name: removing?.reviewer.name ?? '' }) }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="removing = null">{{ t('common.cancel') }}</button>
                    <button class="flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-400 disabled:opacity-60" :disabled="removeBusy" @click="remove">
                        <Fa icon="user-xmark" /> {{ t('reviews.panel.remove') }}
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
