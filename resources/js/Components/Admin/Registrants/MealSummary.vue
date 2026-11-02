<script setup>
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { OTHER_MEAL, useMealOptions } from '@/composables/useMealOptions';

// Registrants › Meals: how many meals of each kind to order (registrants + companions), and every typed "Other"
const props = defineProps({
    registrants: { type: Array, required: true }, // rows of RegistrantController::index
});

const { t, tr, formatNumber } = useI18n();
const { mealOptions } = useMealOptions();

// Suspended accounts are not coming
const coming = computed(() => props.registrants.filter((r) => !r.suspended));

const rows = computed(() => {
    const count = (field, code) => coming.value.filter((r) => r[field] === code).length;
    return [...mealOptions.value.map((m) => ({ code: m.code, name: tr(m.name) })), { code: OTHER_MEAL, name: t('meals.other') }]
        .map((m) => {
            const own = count('meal', m.code);
            const companions = count('companion_meal', m.code);
            return { ...m, own, companions, total: own + companions };
        });
});
const totals = computed(() => ({
    people: coming.value.length + coming.value.filter((r) => r.companion_name).length,
    companions: coming.value.filter((r) => r.companion_name).length,
    notChosen: coming.value.filter((r) => !r.meal).length,
}));
const maxTotal = computed(() => Math.max(1, ...rows.value.map((r) => r.total)));

// What people typed under "Other" (allergies and the like): the kitchen needs each one
const typed = computed(() => coming.value.flatMap((r) => [
    ...(r.meal === OTHER_MEAL ? [{ key: `${r.id}-own`, who: r.name, companionOf: null, text: r.meal_other }] : []),
    ...(r.companion_name && r.companion_meal === OTHER_MEAL ? [{ key: `${r.id}-companion`, who: r.companion_name, companionOf: r.name, text: r.companion_meal_other }] : []),
]));

const tile = 'rounded-xl border border-neutral-200 p-4 dark:border-white/10';
</script>

<template>
    <div class="space-y-6">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.registrants.meals.intro') }}</p>

        <div class="grid gap-3 sm:grid-cols-3">
            <div :class="tile">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.registrants.meals.people') }}</p>
                <p class="mt-1 text-2xl font-semibold">{{ formatNumber(totals.people) }}</p>
            </div>
            <div :class="tile">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.registrants.meals.companions') }}</p>
                <p class="mt-1 text-2xl font-semibold">{{ formatNumber(totals.companions) }}</p>
            </div>
            <div :class="tile">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.registrants.meals.notChosen') }}</p>
                <p class="mt-1 text-2xl font-semibold" :class="{ 'text-amber-600 dark:text-amber-400': totals.notChosen }">{{ formatNumber(totals.notChosen) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-140 text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('admin.registrants.meals.meal') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('admin.registrants.meals.registrants') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('admin.registrants.meals.companions') }}</th>
                        <th class="w-2/5 px-4 py-3 font-medium">{{ t('admin.registrants.meals.total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-white/5">
                    <tr v-for="r in rows" :key="r.code">
                        <td class="px-4 py-3 font-medium">{{ r.name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ formatNumber(r.own) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ formatNumber(r.companions) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100 dark:bg-white/5">
                                    <span class="block h-full rounded-full bg-gold-400 transition-all" :style="{ width: `${(r.total / maxTotal) * 100}%` }" />
                                </span>
                                <span class="w-10 text-right font-semibold tabular-nums">{{ formatNumber(r.total) }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-3">
            <h3 class="flex items-center gap-2 font-semibold"><Fa icon="pen" class="text-gold-500" /> {{ t('admin.registrants.meals.typedTitle') }}</h3>
            <ul v-if="typed.length" class="divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                <li v-for="item in typed" :key="item.key" class="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-3 text-sm">
                    <span class="font-medium">{{ item.who }}</span>
                    <span v-if="item.companionOf" class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.registrants.meals.companionOf', { name: item.companionOf }) }}</span>
                    <span class="basis-full text-neutral-700 sm:basis-auto sm:flex-1 sm:text-right dark:text-neutral-300">{{ item.text }}</span>
                </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed border-neutral-300 px-4 py-8 text-center text-sm text-neutral-500 dark:border-white/15">{{ t('admin.registrants.meals.typedEmpty') }}</p>
        </div>
    </div>
</template>
