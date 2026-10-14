<script setup>
import { computed, reactive } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { useModules } from '@/composables/useModules';
import { importantDates, program } from '@/data/mock';

/*
 * Schedule (admin): the important dates and the conference-day programme shown on the public Schedule page.
 * Mock phase: edits change the shared sample lists, so the public page shows them until the browser reloads.
 */
const { t, formatDate } = useI18n();
const { toast } = useToast();
// Editors can look; only admin changes what the website shows
const canEdit = useModules().accessTo('schedule') === 'full';

const dates = reactive(importantDates);
const items = reactive(program);
const sortedDates = computed(() => [...dates].sort((a, b) => a.date.localeCompare(b.date)));

const blankDate = () => ({ date: '', label: { th: '', en: '' } });
const blankItem = () => ({ time: '', label: { th: '', en: '' } });
const newDate = reactive(blankDate());
const newItem = reactive(blankItem());
const filled = (row, key) => row[key].trim() && row.label.th.trim() && row.label.en.trim();

function addDate() {
    dates.push({ date: newDate.date, label: { ...newDate.label } });
    Object.assign(newDate, blankDate());
    toast('admin.schedule.toast.saved');
}
function addItem() {
    items.push({ time: newItem.time.trim(), label: { ...newItem.label } });
    Object.assign(newItem, blankItem());
    toast('admin.schedule.toast.saved');
}
function remove(list, row) {
    list.splice(list.indexOf(row), 1);
    toast('admin.schedule.toast.removed');
}
function move(i, step) {
    const [row] = items.splice(i, 1);
    items.splice(i + step, 0, row);
}

// Stable keys while the fields are being edited
const keys = new WeakMap();
let nextKey = 0;
const keyOf = (row) => keys.get(row) ?? (keys.set(row, ++nextKey), nextKey);

const isPast = (date) => date < new Date().toISOString().slice(0, 10);
const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800 dark:scheme-dark';
const iconBtn = 'grid size-8 place-items-center rounded-md text-neutral-500 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-25 disabled:hover:bg-transparent dark:text-neutral-400 dark:hover:bg-white/10';
const addBtn = 'inline-flex items-center gap-2 rounded-lg border border-gold-400 px-3 py-2 text-xs font-semibold whitespace-nowrap text-gold-600 transition hover:bg-gold-400 hover:text-black disabled:opacity-40 dark:text-gold-400';
const th = 'px-3 py-3 font-medium';
</script>

<template>
    <Head :title="t('admin.menu.schedule')" />

    <div class="space-y-6">
        <Card class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <PageHeader icon="calendar-days" :title="t('admin.menu.schedule')" :subtitle="t('admin.schedule.subtitle')" class="flex-1" />
                <Link href="/schedule" class="flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20">
                    <Fa icon="arrow-up-right-from-square" /> {{ t('admin.schedule.viewSite') }}
                </Link>
            </div>
        </Card>

        <!-- Important dates (sorted by date on the website) -->
        <Card class="space-y-4">
            <h2 class="text-lg font-semibold"><Fa icon="calendar-days" class="mr-2 text-gold-500" />{{ t('admin.schedule.dates') }}</h2>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.schedule.datesHint') }}</p>
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                <table class="w-full min-w-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                        <tr>
                            <th :class="[th, 'w-44']">{{ t('admin.schedule.date') }}</th>
                            <th :class="th">{{ t('admin.schedule.labelTh') }}</th>
                            <th :class="th">{{ t('admin.schedule.labelEn') }}</th>
                            <th v-if="canEdit" :class="[th, 'w-16']" />
                        </tr>
                    </thead>
                    <TransitionGroup tag="tbody" name="fade" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="row in sortedDates" :key="keyOf(row)" :class="isPast(row.date) ? 'opacity-60' : ''">
                            <td class="px-3 py-2">
                                <input v-model="row.date" type="date" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']">
                                <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(row.date) }}<template v-if="isPast(row.date)"> · {{ t('admin.schedule.past') }}</template></p>
                            </td>
                            <td class="px-3 py-2"><input v-model="row.label.th" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><input v-model="row.label.en" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td v-if="canEdit" class="px-3 py-2"><button :class="iconBtn" class="hover:text-red-500!" :title="t('admin.schedule.remove')" @click="remove(dates, row)"><Fa icon="trash-can" class="text-xs" /></button></td>
                        </tr>
                    </TransitionGroup>
                    <tfoot v-if="canEdit" class="border-t border-neutral-200 bg-gold-50/50 dark:border-white/10 dark:bg-gold-400/5">
                        <tr>
                            <td class="px-3 py-2"><input v-model="newDate.date" type="date" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><input v-model="newDate.label.th" :placeholder="t('admin.schedule.placeholderTh')" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><input v-model="newDate.label.en" :placeholder="t('admin.schedule.placeholderEn')" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><button :class="addBtn" :disabled="!filled(newDate, 'date')" @click="addDate"><Fa icon="plus" /></button></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </Card>

        <!-- Conference-day programme (shown in this order) -->
        <Card class="space-y-4">
            <h2 class="text-lg font-semibold"><Fa icon="clock" class="mr-2 text-gold-500" />{{ t('admin.schedule.program') }}</h2>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.schedule.programHint') }}</p>
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                <table class="w-full min-w-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                        <tr>
                            <th v-if="canEdit" :class="[th, 'w-20']">{{ t('admin.academicTitles.order') }}</th>
                            <th :class="[th, 'w-40']">{{ t('admin.schedule.time') }}</th>
                            <th :class="th">{{ t('admin.schedule.labelTh') }}</th>
                            <th :class="th">{{ t('admin.schedule.labelEn') }}</th>
                            <th v-if="canEdit" :class="[th, 'w-16']" />
                        </tr>
                    </thead>
                    <TransitionGroup tag="tbody" name="fade" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="(row, i) in items" :key="keyOf(row)">
                            <td v-if="canEdit" class="px-3 py-2">
                                <div class="flex">
                                    <button :class="iconBtn" :disabled="i === 0" :title="t('admin.academicTitles.moveUp')" @click="move(i, -1)"><Fa icon="chevron-up" class="text-xs" /></button>
                                    <button :class="iconBtn" :disabled="i === items.length - 1" :title="t('admin.academicTitles.moveDown')" @click="move(i, 1)"><Fa icon="chevron-down" class="text-xs" /></button>
                                </div>
                            </td>
                            <td class="px-3 py-2"><input v-model="row.time" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><input v-model="row.label.th" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><input v-model="row.label.en" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td v-if="canEdit" class="px-3 py-2"><button :class="iconBtn" class="hover:text-red-500!" :title="t('admin.schedule.remove')" @click="remove(items, row)"><Fa icon="trash-can" class="text-xs" /></button></td>
                        </tr>
                    </TransitionGroup>
                    <tfoot v-if="canEdit" class="border-t border-neutral-200 bg-gold-50/50 dark:border-white/10 dark:bg-gold-400/5">
                        <tr>
                            <td class="px-3 py-2 text-xs font-medium text-gold-600 dark:text-gold-400">{{ t('admin.academicTitles.new') }}</td>
                            <td class="px-3 py-2"><input v-model="newItem.time" placeholder="13:00 – 15:00" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><input v-model="newItem.label.th" :placeholder="t('admin.schedule.placeholderTh')" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><input v-model="newItem.label.en" :placeholder="t('admin.schedule.placeholderEn')" :readonly="!canEdit" :class="[input, !canEdit && 'cursor-default border-transparent! bg-transparent!']"></td>
                            <td class="px-3 py-2"><button :class="addBtn" :disabled="!filled(newItem, 'time')" @click="addItem"><Fa icon="plus" /></button></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.schedule.mockNote') }}</p>
        </Card>
    </div>
</template>
