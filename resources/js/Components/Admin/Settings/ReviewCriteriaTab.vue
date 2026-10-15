<script setup>
import { reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';

// Review criteria (ReviewCriterionController): reviewers score each one from 1 to 5, in this order
const props = defineProps({
    criteria: { type: Array, required: true }, // { id, name_th, name_en, description_th, description_en, scores }
});

const { t, formatNumber } = useI18n();
const fields = ['name_th', 'name_en', 'description_th', 'description_en'];
const required = ['name_th', 'name_en'];

// Editable copy of each row, refreshed when the server sends new data
const drafts = reactive({});
const errors = reactive({});
const busy = reactive({});
watch(() => props.criteria, (titles) => {
    titles.forEach((title) => (drafts[title.id] = Object.fromEntries(fields.map((f) => [f, title[f]]))));
}, { immediate: true });

const isDirty = (title) => fields.some((f) => drafts[title.id][f] !== title[f]);
const options = { preserveScroll: true, preserveState: true };

function save(title) {
    router.put(`/admin/settings/review-criteria/${title.id}`, drafts[title.id], {
        ...options,
        onStart: () => (busy[title.id] = true),
        onSuccess: () => (errors[title.id] = {}),
        onError: (e) => (errors[title.id] = e),
        onFinish: () => (busy[title.id] = false),
    });
}

function move(title, direction) {
    router.put(`/admin/settings/review-criteria/${title.id}/move`, { direction }, options);
}

// New title (row at the bottom)
const form = useForm(Object.fromEntries(fields.map((f) => [f, ''])));
function add() {
    form.post('/admin/settings/review-criteria', { ...options, onSuccess: () => form.reset() });
}

// Delete (only criteria nobody has scored)
const removing = ref(null);
const shownRemoving = ref(null);
watch(removing, (value) => value && (shownRemoving.value = value));
function destroy() {
    router.delete(`/admin/settings/review-criteria/${removing.value.id}`, { ...options, onFinish: () => (removing.value = null) });
}

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const iconBtn = 'grid size-8 place-items-center rounded-md text-neutral-500 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-500 dark:text-neutral-400 dark:hover:bg-white/10';
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.reviewCriteria.intro') }}</p>
        <p v-if="!criteria.length" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-400/10 dark:text-amber-200"><Fa icon="circle-info" class="mr-1" />{{ t('admin.reviewCriteria.none') }}</p>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-250 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="w-20 px-4 py-3 font-medium">{{ t('admin.reviewCriteria.order') }}</th>
                        <th v-for="field in fields" :key="field" class="px-2 py-3 font-medium">{{ t(`admin.reviewCriteria.${field}`) }}</th>
                        <th class="px-2 py-3 text-center font-medium">{{ t('admin.reviewCriteria.scores') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <TransitionGroup tag="tbody" name="fade" class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="(title, i) in criteria" :key="title.id">
                        <td class="px-4 py-3">
                            <div class="flex">
                                <button :class="iconBtn" :disabled="i === 0" :title="t('admin.reviewCriteria.moveUp')" @click="move(title, 'up')"><Fa icon="chevron-up" class="text-xs" /></button>
                                <button :class="iconBtn" :disabled="i === criteria.length - 1" :title="t('admin.reviewCriteria.moveDown')" @click="move(title, 'down')"><Fa icon="chevron-down" class="text-xs" /></button>
                            </div>
                        </td>
                        <td v-for="field in fields" :key="field" class="px-2 py-3">
                            <input v-model="drafts[title.id][field]" :class="[input, border(errors[title.id]?.[field])]" @keydown.enter="isDirty(title) && save(title)">
                        </td>
                        <td class="px-2 py-3 text-center">{{ formatNumber(title.scores) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    class="inline-flex items-center gap-2 rounded-lg bg-gold-400 px-3 py-2 text-xs font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                                    :disabled="!isDirty(title) || busy[title.id]"
                                    @click="save(title)"
                                >
                                    <Fa icon="floppy-disk" /> {{ t('common.save') }}
                                </button>
                                <button
                                    :class="iconBtn"
                                    class="hover:text-red-500!"
                                    :disabled="title.scores > 0"
                                    :title="title.scores > 0 ? t('admin.reviewCriteria.inUse') : t('admin.reviewCriteria.delete')"
                                    @click="removing = title"
                                >
                                    <Fa icon="trash-can" class="text-xs" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </TransitionGroup>
                <tfoot class="border-t border-neutral-200 bg-gold-50/50 dark:border-white/10 dark:bg-gold-400/5">
                    <tr>
                        <td class="px-4 py-3 text-xs font-medium text-gold-600 dark:text-gold-400">{{ t('admin.reviewCriteria.new') }}</td>
                        <td v-for="field in fields" :key="field" class="px-2 py-3">
                            <input v-model="form[field]" :placeholder="t(`admin.reviewCriteria.placeholder.${field}`)" :class="[input, border(form.errors[field])]" @keydown.enter="add">
                        </td>
                        <td />
                        <td class="px-4 py-3 text-right">
                            <button
                                class="inline-flex items-center gap-2 rounded-lg border border-gold-400 px-3 py-2 text-xs font-semibold text-gold-600 transition hover:bg-gold-400 hover:text-black disabled:opacity-40 dark:text-gold-400"
                                :disabled="form.processing || required.some((f) => !form[f].trim())"
                                @click="add"
                            >
                                <Fa icon="plus" /> {{ t('admin.reviewCriteria.add') }}
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <ConfirmModal
        :show="!!removing"
        icon="trash-can"
        danger
        :title="t('admin.reviewCriteria.confirmTitle')"
        :message="shownRemoving ? t('admin.reviewCriteria.confirmText', { title: shownRemoving.name_th }) : ''"
        :confirm-label="t('admin.reviewCriteria.delete')"
        @close="removing = null"
        @confirm="destroy"
    />
</template>
