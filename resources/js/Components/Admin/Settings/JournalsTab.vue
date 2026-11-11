<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';

// Partner journals (PartnerJournalController): offered to papers at the first gate; the author who accepts
// submits on the journal's site. A journal already offered to papers can be switched off but not deleted.
const props = defineProps({
    journals: { type: Array, required: true }, // { id, name, url, publisher, contact_*, terms, active, papers }
});

const { t, formatNumber } = useI18n();
const options = { preserveScroll: true, preserveState: true };

const blank = { name: '', url: '', publisher: '', contact_name: '', contact_position: '', contact_email: '', contact_phone: '', terms: '', active: true };
const editing = ref(null); // a journal, or {} for a new one
const form = useForm({ ...blank });
function open(journal) {
    form.clearErrors();
    Object.assign(form, { ...blank, ...(journal ?? {}) });
    editing.value = journal ?? {};
}
function save() {
    const nulls = (data) => Object.fromEntries(Object.entries(data).map(([k, v]) => [k, typeof v === 'string' ? v.trim() || null : v]));
    const done = { ...options, onSuccess: () => (editing.value = null) };
    form.transform(nulls);
    if (editing.value.id) form.put(`/admin/settings/journals/${editing.value.id}`, done);
    else form.post('/admin/settings/journals', done);
}
const move = (j, direction) => router.put(`/admin/settings/journals/${j.id}/move`, { direction }, options);

// Delete (only journals no paper was offered to)
const removing = ref(null);
const shownRemoving = ref(null);
watch(removing, (value) => value && (shownRemoving.value = value));
const destroy = () => router.delete(`/admin/settings/journals/${removing.value.id}`, { ...options, onFinish: () => (removing.value = null) });

const fields = [
    { key: 'name', required: true, wide: true },
    { key: 'url', type: 'url', wide: true },
    { key: 'publisher', wide: true },
    { key: 'contact_name' },
    { key: 'contact_position' },
    { key: 'contact_email' },
    { key: 'contact_phone' },
];
const input = 'w-full rounded-lg border border-white/10 bg-ink-800 px-3 py-2.5 text-sm text-white outline-none focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20';
const iconBtn = 'grid size-8 place-items-center rounded-md text-neutral-500 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-500 dark:text-neutral-400 dark:hover:bg-white/10';
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="max-w-3xl text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.journals.intro') }}</p>
            <button class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="open(null)">
                <Fa icon="plus" /> {{ t('admin.journals.add') }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-300 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="w-20 px-4 py-3 font-medium">{{ t('admin.academicTitles.order') }}</th>
                        <th class="px-3 py-3 font-medium">{{ t('admin.journals.fields.name') }}</th>
                        <th class="px-3 py-3 font-medium">{{ t('admin.journals.fields.publisher') }}</th>
                        <th class="px-3 py-3 font-medium">{{ t('admin.journals.fields.contact_name') }}</th>
                        <th class="px-3 py-3 font-medium">{{ t('admin.journals.contact') }}</th>
                        <th class="px-3 py-3 font-medium">{{ t('admin.journals.fields.terms') }}</th>
                        <th class="px-3 py-3 text-center font-medium">{{ t('admin.journals.status') }}</th>
                        <th class="px-3 py-3 text-center font-medium">{{ t('admin.journals.papersColumn') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <TransitionGroup tag="tbody" name="fade" class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="(j, i) in journals" :key="j.id" :class="j.active ? '' : 'text-neutral-400 dark:text-neutral-500'" class="align-top transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                        <td class="px-4 py-3">
                            <div class="flex">
                                <button :class="iconBtn" :disabled="i === 0" :title="t('admin.academicTitles.moveUp')" @click="move(j, 'up')"><Fa icon="chevron-up" class="text-xs" /></button>
                                <button :class="iconBtn" :disabled="i === journals.length - 1" :title="t('admin.academicTitles.moveDown')" @click="move(j, 'down')"><Fa icon="chevron-down" class="text-xs" /></button>
                            </div>
                        </td>
                        <td class="max-w-72 px-3 py-3">
                            <p class="font-medium">{{ j.name }}</p>
                            <a v-if="j.url" :href="j.url" target="_blank" rel="noopener" class="mt-0.5 inline-flex max-w-full items-center gap-1 text-xs text-sky-700 hover:underline dark:text-sky-300">
                                <span class="truncate">{{ j.url.replace(/^https?:\/\//, '') }}</span> <Fa icon="arrow-up-right-from-square" class="shrink-0 text-[0.6rem]" />
                            </a>
                        </td>
                        <td class="max-w-56 px-3 py-3 text-xs"><span class="line-clamp-3">{{ j.publisher || '—' }}</span></td>
                        <td class="px-3 py-3">
                            <p>{{ j.contact_name || '—' }}</p>
                            <p v-if="j.contact_position" class="text-xs text-neutral-500 dark:text-neutral-400">{{ j.contact_position }}</p>
                        </td>
                        <td class="px-3 py-3 text-xs">
                            <p v-if="j.contact_email" class="break-all">{{ j.contact_email }}</p>
                            <p v-if="j.contact_phone" class="whitespace-nowrap">{{ j.contact_phone }}</p>
                            <span v-if="!j.contact_email && !j.contact_phone">—</span>
                        </td>
                        <td class="max-w-64 px-3 py-3 text-xs"><span class="line-clamp-3" :title="j.terms">{{ j.terms || '—' }}</span></td>
                        <td class="px-3 py-3 text-center">
                            <span
                                :class="j.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-neutral-200 text-neutral-600 dark:bg-white/10 dark:text-neutral-300'"
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
                            >{{ t(j.active ? 'admin.journals.active' : 'admin.journals.inactive') }}</span>
                        </td>
                        <td class="px-3 py-3 text-center">{{ formatNumber(j.papers) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <button :class="iconBtn" :title="t('admin.journals.edit')" @click="open(j)"><Fa icon="pen" class="text-xs" /></button>
                                <button
                                    :class="iconBtn"
                                    class="hover:text-red-500!"
                                    :disabled="j.papers > 0"
                                    :title="j.papers > 0 ? t('admin.journals.inUse') : t('admin.journals.delete')"
                                    @click="removing = j"
                                >
                                    <Fa icon="trash-can" class="text-xs" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </TransitionGroup>
            </table>
        </div>
    </div>

    <Modal :show="!!editing" max-width="max-w-2xl" @close="editing = null">
        <form class="p-6 sm:p-8" @submit.prevent="save">
            <h2 class="mb-5 text-xl font-semibold">{{ t(editing?.id ? 'admin.journals.edit' : 'admin.journals.add') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label v-for="field in fields" :key="field.key" :class="field.wide ? 'sm:col-span-2' : ''" class="block">
                    <span class="mb-1.5 block text-sm text-neutral-300">{{ t(`admin.journals.fields.${field.key}`) }}<span v-if="field.required" class="text-red-400"> *</span></span>
                    <input v-model="form[field.key]" :type="field.type ?? 'text'" :required="field.required" :class="input">
                    <span v-if="form.errors[field.key]" class="mt-1 block text-xs text-red-400">{{ t(form.errors[field.key]) }}</span>
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm text-neutral-300">{{ t('admin.journals.fields.terms') }}</span>
                    <textarea v-model="form.terms" rows="3" maxlength="2000" :class="input" />
                    <span class="mt-1 block text-xs text-neutral-400">{{ t('admin.journals.termsHint') }}</span>
                </label>
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <input v-model="form.active" type="checkbox" class="size-4 accent-gold-400">
                    {{ t('admin.journals.fields.active') }}
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="editing = null">{{ t('common.cancel') }}</button>
                <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing || !form.name.trim()">
                    <Fa icon="floppy-disk" /> {{ t('common.save') }}
                </button>
            </div>
        </form>
    </Modal>

    <ConfirmModal
        :show="!!removing"
        icon="trash-can"
        danger
        :title="t('admin.journals.confirmTitle')"
        :message="shownRemoving ? t('admin.journals.confirmText', { name: shownRemoving.name }) : ''"
        :confirm-label="t('admin.journals.delete')"
        @close="removing = null"
        @confirm="destroy"
    />
</template>
