<script setup>
import { reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';

// Meal preferences (MealOptionController): the choices on the sign-up form, for registrants and companions
const props = defineProps({
    meals: { type: Array, required: true }, // { id, code, name_th, name_en, users }
});

const { t, formatNumber } = useI18n();
const fields = ['name_th', 'name_en'];
const options = { preserveScroll: true, preserveState: true };

// Editable copy of each row, refreshed when the server sends new data
const drafts = reactive({});
const errors = reactive({});
const busy = reactive({});
watch(() => props.meals, (list) => {
    list.forEach((m) => (drafts[m.id] = { name_th: m.name_th, name_en: m.name_en }));
}, { immediate: true });
const isDirty = (m) => fields.some((f) => drafts[m.id][f] !== m[f]);

function save(m) {
    router.put(`/admin/settings/meals/${m.id}`, drafts[m.id], {
        ...options,
        onStart: () => (busy[m.id] = true),
        onSuccess: () => (errors[m.id] = {}),
        onError: (e) => (errors[m.id] = e),
        onFinish: () => (busy[m.id] = false),
    });
}
const move = (m, direction) => router.put(`/admin/settings/meals/${m.id}/move`, { direction }, options);

const form = useForm({ name_th: '', name_en: '' });
const add = () => form.post('/admin/settings/meals', { ...options, onSuccess: () => form.reset() });

// Delete (only ones nobody chose)
const removing = ref(null);
const shownRemoving = ref(null);
watch(removing, (value) => value && (shownRemoving.value = value));
const destroy = () => router.delete(`/admin/settings/meals/${removing.value.id}`, { ...options, onFinish: () => (removing.value = null) });

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const iconBtn = 'grid size-8 place-items-center rounded-md text-neutral-500 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-500 dark:text-neutral-400 dark:hover:bg-white/10';
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.meals.intro') }}</p>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-200 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="w-20 px-4 py-3 font-medium">{{ t('admin.academicTitles.order') }}</th>
                        <th class="px-2 py-3 font-medium">{{ t('admin.meals.name_th') }}</th>
                        <th class="px-2 py-3 font-medium">{{ t('admin.meals.name_en') }}</th>
                        <th class="px-2 py-3 text-center font-medium">{{ t('admin.meals.users') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <TransitionGroup tag="tbody" name="fade" class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="(m, i) in meals" :key="m.id">
                        <td class="px-4 py-3">
                            <div class="flex">
                                <button :class="iconBtn" :disabled="i === 0" :title="t('admin.academicTitles.moveUp')" @click="move(m, 'up')"><Fa icon="chevron-up" class="text-xs" /></button>
                                <button :class="iconBtn" :disabled="i === meals.length - 1" :title="t('admin.academicTitles.moveDown')" @click="move(m, 'down')"><Fa icon="chevron-down" class="text-xs" /></button>
                            </div>
                        </td>
                        <td v-for="field in fields" :key="field" class="px-2 py-3">
                            <input v-model="drafts[m.id][field]" :class="[input, border(errors[m.id]?.[field])]" @keydown.enter="isDirty(m) && save(m)">
                        </td>
                        <td class="px-2 py-3 text-center">{{ formatNumber(m.users) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    class="inline-flex items-center gap-2 rounded-lg bg-gold-400 px-3 py-2 text-xs font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
                                    :disabled="!isDirty(m) || busy[m.id]"
                                    @click="save(m)"
                                >
                                    <Fa icon="floppy-disk" /> {{ t('common.save') }}
                                </button>
                                <button
                                    :class="iconBtn"
                                    class="hover:text-red-500!"
                                    :disabled="m.users > 0"
                                    :title="m.users > 0 ? t('admin.meals.inUse') : t('admin.meals.delete')"
                                    @click="removing = m"
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
                            <input v-model="form[field]" :placeholder="t(`admin.meals.placeholder.${field}`)" :class="[input, border(form.errors[field])]" @keydown.enter="add">
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
        <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.meals.otherNote') }}</p>
    </div>

    <ConfirmModal
        :show="!!removing"
        icon="trash-can"
        danger
        :title="t('admin.meals.confirmTitle')"
        :message="shownRemoving ? t('admin.meals.confirmText', { name: shownRemoving.name_th }) : ''"
        :confirm-label="t('admin.meals.delete')"
        @close="removing = null"
        @confirm="destroy"
    />
</template>
