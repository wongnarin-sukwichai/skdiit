<script setup>
import { reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';

// Affiliations (AffiliationController): the sign-up list, plus names registrants typed under "Other"
const props = defineProps({
    affiliations: { type: Array, required: true }, // { id, code, name_th, name_en, users }
    typed: { type: Array, required: true }, // { name, users } typed under "Other"
});

const { t, formatNumber } = useI18n();
const fields = ['name_th', 'name_en'];
const options = { preserveScroll: true, preserveState: true };

// Editable copy of each row, refreshed when the server sends new data
const drafts = reactive({});
const errors = reactive({});
const busy = reactive({});
watch(() => props.affiliations, (list) => {
    list.forEach((a) => (drafts[a.id] = { name_th: a.name_th, name_en: a.name_en }));
}, { immediate: true });
const isDirty = (a) => fields.some((f) => drafts[a.id][f] !== a[f]);

function save(a) {
    router.put(`/admin/settings/affiliations/${a.id}`, drafts[a.id], {
        ...options,
        onStart: () => (busy[a.id] = true),
        onSuccess: () => (errors[a.id] = {}),
        onError: (e) => (errors[a.id] = e),
        onFinish: () => (busy[a.id] = false),
    });
}
const move = (a, direction) => router.put(`/admin/settings/affiliations/${a.id}/move`, { direction }, options);

const form = useForm({ name_th: '', name_en: '' });
const add = () => form.post('/admin/settings/affiliations', { ...options, onSuccess: () => form.reset() });

// Delete (only unused ones)
const removing = ref(null);
const shownRemoving = ref(null);
watch(removing, (value) => value && (shownRemoving.value = value));
const destroy = () => router.delete(`/admin/settings/affiliations/${removing.value.id}`, { ...options, onFinish: () => (removing.value = null) });

// "Other" names: add one to the list and move everyone who typed it
const adopting = ref(null);
const adoptForm = useForm({ typed: '', name_th: '', name_en: '' });
function openAdopt(item) {
    adoptForm.clearErrors();
    Object.assign(adoptForm, { typed: item.name, name_th: item.name, name_en: item.name });
    adopting.value = item;
}
const adopt = () => adoptForm.post('/admin/settings/affiliations/adopt', { ...options, onSuccess: () => (adopting.value = null) });

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const iconBtn = 'grid size-8 place-items-center rounded-md text-neutral-500 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-500 dark:text-neutral-400 dark:hover:bg-white/10';
const darkInput = 'w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm outline-none focus:border-gold-400';
</script>

<template>
    <div class="space-y-8">
        <div class="space-y-4">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.affiliations.intro') }}</p>

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                <table class="w-full min-w-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                        <tr>
                            <th class="w-20 px-4 py-3 font-medium">{{ t('admin.academicTitles.order') }}</th>
                            <th class="px-2 py-3 font-medium">{{ t('admin.affiliations.name_th') }}</th>
                            <th class="px-2 py-3 font-medium">{{ t('admin.affiliations.name_en') }}</th>
                            <th class="px-2 py-3 text-center font-medium">{{ t('admin.affiliations.users') }}</th>
                            <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                        </tr>
                    </thead>
                    <TransitionGroup tag="tbody" name="fade" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="(a, i) in affiliations" :key="a.id">
                            <td class="px-4 py-3">
                                <div class="flex">
                                    <button :class="iconBtn" :disabled="i === 0" :title="t('admin.academicTitles.moveUp')" @click="move(a, 'up')"><Fa icon="chevron-up" class="text-xs" /></button>
                                    <button :class="iconBtn" :disabled="i === affiliations.length - 1" :title="t('admin.academicTitles.moveDown')" @click="move(a, 'down')"><Fa icon="chevron-down" class="text-xs" /></button>
                                </div>
                            </td>
                            <td v-for="field in fields" :key="field" class="px-2 py-3">
                                <input v-model="drafts[a.id][field]" :class="[input, border(errors[a.id]?.[field])]" @keydown.enter="isDirty(a) && save(a)">
                            </td>
                            <td class="px-2 py-3 text-center">{{ formatNumber(a.users) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        class="inline-flex items-center gap-2 rounded-lg bg-gold-400 px-3 py-2 text-xs font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                                        :disabled="!isDirty(a) || busy[a.id]"
                                        @click="save(a)"
                                    >
                                        <Fa icon="floppy-disk" /> {{ t('common.save') }}
                                    </button>
                                    <button
                                        :class="iconBtn"
                                        class="hover:text-red-500!"
                                        :disabled="a.users > 0"
                                        :title="a.users > 0 ? t('admin.affiliations.inUse') : t('admin.affiliations.delete')"
                                        @click="removing = a"
                                    >
                                        <Fa icon="trash-can" class="text-xs" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </TransitionGroup>
                    <tfoot class="border-t border-neutral-200 bg-gold-50/50 dark:border-white/10 dark:bg-gold-400/5">
                        <tr>
                            <td class="px-4 py-3 text-xs font-medium text-gold-600 dark:text-gold-400">{{ t('admin.academicTitles.new') }}</td>
                            <td v-for="field in fields" :key="field" class="px-2 py-3">
                                <input v-model="form[field]" :placeholder="t(`admin.affiliations.placeholder.${field}`)" :class="[input, border(form.errors[field])]" @keydown.enter="add">
                            </td>
                            <td />
                            <td class="px-4 py-3 text-right">
                                <button
                                    class="inline-flex items-center gap-2 rounded-lg border border-gold-400 px-3 py-2 text-xs font-semibold text-gold-600 transition hover:bg-gold-400 hover:text-black disabled:opacity-40 dark:text-gold-400"
                                    :disabled="form.processing || fields.some((f) => !form[f].trim())"
                                    @click="add"
                                >
                                    <Fa icon="plus" /> {{ t('admin.academicTitles.add') }}
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.affiliations.otherNote') }}</p>
        </div>

        <!-- Names typed under "Other" -->
        <div class="space-y-3">
            <h3 class="flex items-center gap-2 font-semibold"><Fa icon="pen" class="text-gold-500" /> {{ t('admin.affiliations.typedTitle') }}</h3>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.affiliations.typedHint') }}</p>
            <ul v-if="typed.length" class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                <li v-for="item in typed" :key="item.name" class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
                    <span class="min-w-0 flex-1 font-medium">{{ item.name }}</span>
                    <span class="text-neutral-500 dark:text-neutral-400">{{ t('admin.affiliations.people', { n: formatNumber(item.users) }) }}</span>
                    <button class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium transition hover:border-gold-400 hover:text-gold-600 dark:border-white/15 dark:hover:text-gold-400" @click="openAdopt(item)">
                        <Fa icon="plus" /> {{ t('admin.affiliations.adopt') }}
                    </button>
                </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed border-neutral-300 px-4 py-8 text-center text-sm text-neutral-500 dark:border-white/15">{{ t('admin.affiliations.typedEmpty') }}</p>
        </div>
    </div>

    <ConfirmModal
        :show="!!removing"
        icon="trash-can"
        danger
        :title="t('admin.affiliations.confirmTitle')"
        :message="shownRemoving ? t('admin.affiliations.confirmText', { name: shownRemoving.name_th }) : ''"
        :confirm-label="t('admin.affiliations.delete')"
        @close="removing = null"
        @confirm="destroy"
    />

    <ConfirmModal
        :show="!!adopting"
        icon="building-columns"
        :title="t('admin.affiliations.adoptTitle')"
        :message="adopting ? t('admin.affiliations.adoptText', { name: adopting.name, n: adopting.users }) : ''"
        :confirm-label="t('admin.affiliations.adopt')"
        :processing="adoptForm.processing"
        @close="adopting = null"
        @confirm="adopt"
    >
        <div class="mt-4 space-y-3">
            <label class="block text-sm">
                <span class="mb-1 block text-neutral-300">{{ t('admin.affiliations.name_th') }}</span>
                <input v-model="adoptForm.name_th" :class="darkInput">
                <span v-if="adoptForm.errors.name_th" class="mt-1 block text-xs text-red-400">{{ t(adoptForm.errors.name_th) }}</span>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-neutral-300">{{ t('admin.affiliations.name_en') }}</span>
                <input v-model="adoptForm.name_en" :class="darkInput">
                <span v-if="adoptForm.errors.name_en" class="mt-1 block text-xs text-red-400">{{ t(adoptForm.errors.name_en) }}</span>
            </label>
        </div>
    </ConfirmModal>
</template>
