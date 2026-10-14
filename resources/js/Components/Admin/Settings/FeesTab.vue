<script setup>
import { reactive } from 'vue';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';
import { paymentFees } from '@/data/mock';

/*
 * Fees (sample until the client confirms them): normal and early-bird prices per publishing option,
 * days to pay after a fee is issued, and an optional attendee fee. Saving updates the shared sample,
 * so the Payments pages use the new values until the browser reloads.
 */
const { t } = useI18n();
const { toast } = useToast();

const fees = reactive(paymentFees);
const draft = reactive(JSON.parse(JSON.stringify(paymentFees)));

function save() {
    Object.assign(fees, JSON.parse(JSON.stringify(draft)));
    toast('admin.fees.saved');
}

const input = 'rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800 dark:scheme-dark';
const box = 'space-y-4 rounded-xl border border-neutral-200 p-5 dark:border-white/10';
const label = 'mb-1.5 block text-sm font-medium';
const hint = 'mt-1 text-xs text-neutral-500 dark:text-neutral-400';
</script>

<template>
    <form class="max-w-3xl space-y-6" @submit.prevent="save">
        <p class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
            <Fa icon="circle-info" class="mt-0.5" /> {{ t('admin.fees.pending') }}
        </p>

        <!-- Normal price per publishing option -->
        <section :class="box">
            <h3 class="font-semibold">{{ t('admin.fees.normal') }}</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <label v-for="o in ['a', 'b']" :key="o" class="block">
                    <span :class="label">{{ t(`admin.payments.optionName.${o}`) }}</span>
                    <span class="flex items-center gap-2"><input v-model.number="draft[o]" type="number" min="0" step="100" :class="[input, 'w-40']"> {{ t('admin.payments.baht') }}</span>
                </label>
            </div>
        </section>

        <!-- Early bird -->
        <section :class="box">
            <label class="flex items-center gap-3 font-semibold">
                <input v-model="draft.earlyBird.enabled" type="checkbox" class="size-4 accent-gold-500"> {{ t('admin.fees.earlyBird') }}
            </label>
            <Transition name="fade">
                <div v-if="draft.earlyBird.enabled" class="grid gap-4 sm:grid-cols-3">
                    <label v-for="o in ['a', 'b']" :key="o" class="block">
                        <span :class="label">{{ t(`admin.payments.optionShort.${o}`) }}</span>
                        <span class="flex items-center gap-2"><input v-model.number="draft.earlyBird[o]" type="number" min="0" step="100" :class="[input, 'w-32']"> {{ t('admin.payments.baht') }}</span>
                    </label>
                    <label class="block">
                        <span :class="label">{{ t('admin.fees.earlyUntil') }}</span>
                        <input v-model="draft.earlyBird.until" type="date" :class="input">
                    </label>
                    <p :class="hint" class="sm:col-span-3">{{ t('admin.fees.earlyHint') }}</p>
                </div>
            </Transition>
        </section>

        <!-- Payment period -->
        <section :class="box">
            <label class="block">
                <span :class="label">{{ t('admin.fees.dueDays') }}</span>
                <span class="flex items-center gap-2"><input v-model.number="draft.dueDays" type="number" min="1" max="90" :class="[input, 'w-24']"> {{ t('admin.fees.days') }}</span>
                <span :class="hint" class="block">{{ t('admin.fees.dueHint') }}</span>
            </label>
        </section>

        <!-- Attendee fee (waiting for the client) -->
        <section :class="box">
            <label class="flex items-center gap-3 font-semibold">
                <input v-model="draft.attendee.enabled" type="checkbox" class="size-4 accent-gold-500"> {{ t('admin.fees.attendee') }}
            </label>
            <Transition name="fade">
                <label v-if="draft.attendee.enabled" class="block">
                    <span class="flex items-center gap-2"><input v-model.number="draft.attendee.amount" type="number" min="0" step="100" :class="[input, 'w-40']"> {{ t('admin.payments.baht') }}</span>
                </label>
            </Transition>
            <p :class="hint">{{ t('admin.fees.attendeeHint') }}</p>
        </section>

        <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
            <Fa icon="floppy-disk" /> {{ t('common.save') }}
        </button>
    </form>
</template>
