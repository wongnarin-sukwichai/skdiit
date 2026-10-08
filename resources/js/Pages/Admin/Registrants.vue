<script setup>
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCards from '@/Components/Admin/StatCards.vue';
import CheckMark from '@/Components/CheckMark.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import RegistrantDetailModal from '@/Components/RegistrantDetailModal.vue';
import { useI18n } from '@/i18n';
import { useRegistrantTable } from '@/composables/useRegistrantTable';
import { useModules } from '@/composables/useModules';
import { affiliationByKey } from '@/data/mock';

const { t, tr, formatDate, formatNumber } = useI18n();
const { form, perPage, page, total, lastPage, rows, search, reset } = useRegistrantTable();
const selected = ref(null);

// Editor and finance can only view this list
const { accessTo } = useModules();
const canEdit = computed(() => accessTo('registrants') === 'full');

// Row selection (for bulk actions later)
const checked = ref(new Set());
const allChecked = computed(() => rows.value.length > 0 && rows.value.every((r) => checked.value.has(r.id)));
function toggleAll() {
    const next = new Set(checked.value);
    rows.value.forEach((r) => (allChecked.value ? next.delete(r.id) : next.add(r.id)));
    checked.value = next;
}
function toggle(id) {
    const next = new Set(checked.value);
    next.has(id) ? next.delete(id) : next.add(id);
    checked.value = next;
}
watch([page, perPage], () => (checked.value = new Set()));

const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 py-2.5 pr-3 pl-11 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <Head :title="t('admin.registrants.title')" />

    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="users" :title="t('admin.registrants.title')" :subtitle="t('admin.registrants.subtitle')" />
            <StatCards />
        </Card>

        <Card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Fa icon="magnifying-glass" class="text-gold-400" /> {{ t('admin.registrants.searchTitle') }}
                </h2>
                <button v-if="canEdit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black opacity-60" disabled :title="t('common.comingSoon')">
                    <Fa icon="plus" /> {{ t('admin.registrants.add') }}
                </button>
            </div>

            <form class="mb-5 grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]" @submit.prevent="search">
                <label class="relative">
                    <Fa icon="user" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                    <input v-model="form.name" :placeholder="t('registrants.searchName')" :class="input">
                </label>
                <label class="relative">
                    <Fa icon="building-columns" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                    <input v-model="form.affiliation" :placeholder="t('registrants.searchAffiliation')" :class="input">
                </label>
                <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-8 py-2.5 font-semibold text-black transition hover:bg-gold-300">
                    <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
                </button>
                <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-6 py-2.5 transition hover:border-gold-400 dark:border-white/20" @click="reset">
                    <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
                </button>
            </form>

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
                <table class="w-full min-w-[1000px] text-sm">
                    <thead class="bg-gold-300 text-left text-black dark:bg-gold-400">
                        <tr>
                            <th class="w-12 px-4 py-3">
                                <input type="checkbox" class="size-4 accent-black" :checked="allChecked" @change="toggleAll">
                            </th>
                            <th class="px-3 py-3 font-semibold">{{ t('registrants.no') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('registrants.name') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('registrants.affiliation') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.attend') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.submit') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.status') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ t('registrants.registeredAt') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ t('registrants.actions') }}</th>
                        </tr>
                    </thead>
                    <Transition name="fade" mode="out-in">
                        <tbody :key="`${page}-${perPage}-${total}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                :class="checked.has(row.id) ? 'bg-gold-50 dark:bg-gold-400/10' : 'odd:bg-neutral-50 dark:odd:bg-white/[0.02]'"
                                class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5"
                            >
                                <td class="px-4 py-2.5">
                                    <input type="checkbox" class="size-4 accent-gold-400" :checked="checked.has(row.id)" @change="toggle(row.id)">
                                </td>
                                <td class="px-3 py-2.5 text-neutral-500 dark:text-neutral-400">{{ row.no }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ tr(row.name) }}</td>
                                <td class="px-3 py-2.5 text-neutral-600 dark:text-neutral-300">{{ tr(affiliationByKey[row.affiliation]) }}</td>
                                <td class="px-3 py-2.5 text-center"><CheckMark :checked="row.attend" /></td>
                                <td class="px-3 py-2.5 text-center"><CheckMark :checked="row.submitted" /></td>
                                <td class="px-3 py-2.5 text-center"><StatusBadge :status="row.status" /></td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ formatDate(row.registeredAt) }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center justify-center gap-2">
                                        <button :class="iconBtn" :title="t('registrants.view')" @click="selected = row"><Fa icon="eye" class="text-xs" /></button>
                                        <button v-if="canEdit" :class="[iconBtn, 'opacity-50']" :title="`${t('admin.registrants.edit')} · ${t('common.comingSoon')}`" disabled><Fa icon="pen" class="text-xs" /></button>
                                        <button v-if="canEdit" :class="[iconBtn, 'border-transparent opacity-50 dark:border-transparent']" :title="`${t('admin.registrants.more')} · ${t('common.comingSoon')}`" disabled><Fa icon="ellipsis" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="9" class="px-4 py-12 text-center text-neutral-500">{{ t('registrants.empty') }}</td>
                            </tr>
                        </tbody>
                    </Transition>
                </table>
            </div>

            <div class="mt-5 flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="flex items-center gap-3 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ t('registrants.show') }}
                    <select v-model.number="perPage" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-neutral-800 outline-none focus:border-gold-400 dark:border-white/15 dark:bg-ink-800 dark:text-white">
                        <option v-for="n in [10, 25, 50]" :key="n" :value="n">{{ n }}</option>
                    </select>
                    {{ t('registrants.ofTotal', { total: formatNumber(total) }) }}
                </div>
                <Pagination v-model="page" :last-page="lastPage" />
            </div>
        </Card>
    </div>

    <RegistrantDetailModal :registrant="selected" @close="selected = null" />
</template>
