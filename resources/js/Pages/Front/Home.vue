<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import CheckMark from '@/Components/CheckMark.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import { useI18n } from '@/i18n';
import { useRegistrantTable } from '@/composables/useRegistrantTable';
import { useAuthModal } from '@/composables/useAuthModal';
import { affiliationByKey, conference } from '@/data/mock';

const { t, tr, formatDate, formatNumber } = useI18n();
const { openLogin, openRegister } = useAuthModal();
const user = computed(() => usePage().props.auth.user);
const { form, perPage, page, total, lastPage, rows, search, reset } = useRegistrantTable();
</script>

<template>
    <Head :title="t('nav.home')" />

    <!-- Hero -->
    <!-- The building / SKDIIT 2027 emblem comes from the page background (FrontLayout) -->
    <section class="relative">
        <div class="mx-auto max-w-[1600px] px-4 pt-10 pb-14 sm:px-6 lg:px-8 lg:pt-14">
            <div class="lg:max-w-[60%]">
                <p class="mb-3 text-xs font-semibold tracking-[0.35em] text-gold-400 uppercase sm:text-sm">{{ t('hero.kicker') }}</p>
                <h1 class="text-3xl leading-tight font-bold text-white [text-shadow:0_2px_12px_rgba(0,0,0,.8)] sm:text-5xl">{{ t('hero.title') }}</h1>
                <p class="mt-3 text-lg text-neutral-200 [text-shadow:0_1px_8px_rgba(0,0,0,.9)] sm:text-xl">{{ t('hero.subtitle') }}</p>

                <!-- Auth shortcuts for mobile/tablet; on desktop they live in the header -->
                <div v-if="!user" class="mt-5 grid grid-cols-2 gap-3 sm:flex lg:hidden">
                    <button
                        class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-gold-400/70 bg-black/40 px-5 py-2.5 text-sm text-white transition hover:bg-gold-400/10"
                        @click="openLogin"
                    >
                        <Fa :icon="['far', 'user']" /> {{ t('auth.login') }}
                    </button>
                    <button
                        class="flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300"
                        @click="openRegister"
                    >
                        <Fa icon="user-plus" /> {{ t('auth.register') }}
                    </button>
                </div>

                <ul class="mt-6 flex flex-wrap gap-x-7 gap-y-3 text-sm text-neutral-200">
                    <li class="flex items-center gap-2">
                        <Fa icon="calendar-days" class="text-lg text-gold-400" />
                        {{ formatDate(conference.date, { day: 'numeric', month: 'long', year: 'numeric' }) }}
                    </li>
                    <li class="flex items-center gap-2">
                        <Fa icon="location-dot" class="text-lg text-gold-400" />
                        {{ tr(conference.venue) }}
                    </li>
                    <li class="flex items-center gap-2">
                        <Fa :icon="['far', 'file-lines']" class="text-lg text-gold-400" />
                        {{ t('hero.online') }}
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Registrant list -->
    <section class="mx-auto max-w-[1600px] px-4 pb-6 sm:px-6 lg:px-8">
        <div class="relative rounded-2xl border border-gold-400/25 bg-ink-900/90 p-4 shadow-[0_0_50px_rgba(245,184,0,.08)] backdrop-blur sm:p-6">
            <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-center">
                <div class="flex items-center gap-4 xl:mr-auto">
                    <Fa icon="users" class="text-4xl text-gold-400" />
                    <div>
                        <h2 class="text-2xl font-bold text-white">{{ t('registrants.title') }}</h2>
                        <p class="text-sm text-neutral-400">{{ t('registrants.subtitle') }}</p>
                    </div>
                </div>

                <form class="grid gap-3 sm:grid-cols-2 xl:flex" @submit.prevent="search">
                    <label class="relative">
                        <Fa icon="magnifying-glass" class="absolute top-1/2 left-3.5 -translate-y-1/2 text-neutral-500" />
                        <input
                            v-model="form.name"
                            :placeholder="t('registrants.searchName')"
                            class="w-full rounded-lg border border-white/10 bg-ink-800 py-2.5 pr-3 pl-10 text-sm outline-none focus:border-gold-400 xl:w-64"
                        >
                    </label>
                    <label class="relative">
                        <Fa icon="building-columns" class="absolute top-1/2 left-3.5 -translate-y-1/2 text-neutral-500" />
                        <input
                            v-model="form.affiliation"
                            :placeholder="t('registrants.searchAffiliation')"
                            class="w-full rounded-lg border border-white/10 bg-ink-800 py-2.5 pr-3 pl-10 text-sm outline-none focus:border-gold-400 xl:w-64"
                        >
                    </label>
                    <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-6 py-2.5 font-semibold text-black transition hover:bg-gold-300">
                        <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
                    </button>
                    <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-white/20 px-6 py-2.5 transition hover:border-gold-400" @click="reset">
                        <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-gold-400 text-left text-black">
                        <tr>
                            <th class="px-4 py-3 font-semibold">{{ t('registrants.no') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ t('registrants.name') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ t('registrants.affiliation') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ t('registrants.attend') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ t('registrants.submit') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ t('registrants.status') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ t('registrants.registeredAt') }}</th>
                        </tr>
                    </thead>
                    <Transition name="fade" mode="out-in">
                        <tbody :key="`${page}-${perPage}-${total}`" class="divide-y divide-white/5">
                            <tr v-for="row in rows" :key="row.id" class="transition odd:bg-white/[0.02] hover:bg-gold-400/5">
                                <td class="px-4 py-2.5 text-neutral-400">{{ row.no }}</td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-white">{{ tr(row.name) }}</td>
                                <td class="px-4 py-2.5 text-neutral-200">{{ tr(affiliationByKey[row.affiliation]) }}</td>
                                <td class="px-4 py-2.5 text-center"><CheckMark :checked="row.attend" /></td>
                                <td class="px-4 py-2.5 text-center"><CheckMark :checked="row.submitted" /></td>
                                <td class="px-4 py-2.5 text-center"><StatusBadge :status="row.status" /></td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-neutral-300">{{ formatDate(row.registeredAt) }}</td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-12 text-center text-neutral-400">{{ t('registrants.empty') }}</td>
                            </tr>
                        </tbody>
                    </Transition>
                </table>
            </div>

            <div class="mt-5 flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="flex items-center gap-3 text-sm text-neutral-400">
                    {{ t('registrants.show') }}
                    <select v-model.number="perPage" class="rounded-md border border-white/15 bg-ink-800 px-3 py-1.5 text-white outline-none focus:border-gold-400">
                        <option v-for="n in [10, 25, 50]" :key="n" :value="n">{{ n }}</option>
                    </select>
                    {{ t('registrants.ofTotal', { total: formatNumber(total) }) }}
                </div>
                <Pagination v-model="page" :last-page="lastPage" />
            </div>
        </div>
    </section>
</template>
