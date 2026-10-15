<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

// Important dates and the conference-day programme on the public Schedule page (SiteContentController::updateSchedule)
const { t } = useI18n();
const site = useSite();

const clone = (value) => JSON.parse(JSON.stringify(value));
const form = useForm({ dates: clone(site.value.dates), program: clone(site.value.program) });
watch(() => [site.value.dates, site.value.program], ([dates, program]) => {
    form.defaults({ dates: clone(dates), program: clone(program) });
    form.reset();
});

function submit() {
    form.put('/admin/settings/site/schedule', { preserveScroll: true, preserveState: true });
}
function move(i, step) {
    const [row] = form.program.splice(i, 1);
    form.program.splice(i + step, 0, row);
}

const isPast = (date) => date && date < new Date().toISOString().slice(0, 10);
const err = (key) => form.errors[key];
const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800 dark:scheme-dark';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const iconBtn = 'grid size-8 shrink-0 place-items-center rounded-md text-neutral-500 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-25 disabled:hover:bg-transparent dark:text-neutral-400 dark:hover:bg-white/10';
const addBtn = 'inline-flex items-center gap-2 rounded-lg border border-dashed border-gold-400 px-3 py-2 text-xs text-gold-600 transition hover:bg-gold-50 dark:text-gold-400 dark:hover:bg-gold-400/10';
const box = 'space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-white/10';
</script>

<template>
    <form class="max-w-5xl space-y-6" @submit.prevent="submit">
        <!-- Important dates (saved in date order) -->
        <section :class="box">
            <h3 class="flex items-center gap-2 font-semibold"><Fa icon="calendar-days" class="text-gold-500" /> {{ t('admin.schedule.dates') }}</h3>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.schedule.datesHint') }}</p>
            <TransitionGroup name="fade" tag="ul" class="space-y-2">
                <li v-for="(row, i) in form.dates" :key="i" class="grid items-start gap-2 md:grid-cols-[11rem_1fr_1fr_auto]">
                    <div>
                        <input v-model="row.date" type="date" :aria-label="t('admin.schedule.date')" :class="[input, border(err(`dates.${i}.date`))]">
                        <span v-if="isPast(row.date)" class="text-xs text-neutral-400">{{ t('admin.schedule.past') }}</span>
                    </div>
                    <input v-model="row.label.th" maxlength="255" :placeholder="t('admin.schedule.placeholderTh')" :class="[input, border(err(`dates.${i}.label.th`))]">
                    <input v-model="row.label.en" maxlength="255" :placeholder="t('admin.schedule.placeholderEn')" :class="[input, border(err(`dates.${i}.label.en`))]">
                    <button type="button" :class="iconBtn" class="hover:text-red-500!" :title="t('admin.schedule.remove')" @click="form.dates.splice(i, 1)"><Fa icon="trash-can" class="text-xs" /></button>
                </li>
            </TransitionGroup>
            <button type="button" :class="addBtn" @click="form.dates.push({ date: '', label: { th: '', en: '' } })"><Fa icon="plus" /> {{ t('admin.schedule.addDate') }}</button>
        </section>

        <!-- Conference-day programme (shown in this order) -->
        <section :class="box">
            <h3 class="flex items-center gap-2 font-semibold"><Fa icon="clock" class="text-gold-500" /> {{ t('admin.schedule.program') }}</h3>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.schedule.programHint') }}</p>
            <TransitionGroup name="fade" tag="ul" class="space-y-2">
                <li v-for="(row, i) in form.program" :key="i" class="grid items-start gap-2 md:grid-cols-[auto_11rem_1fr_1fr_auto]">
                    <div class="flex">
                        <button type="button" :class="iconBtn" :disabled="i === 0" :title="t('admin.academicTitles.moveUp')" @click="move(i, -1)"><Fa icon="chevron-up" class="text-xs" /></button>
                        <button type="button" :class="iconBtn" :disabled="i === form.program.length - 1" :title="t('admin.academicTitles.moveDown')" @click="move(i, 1)"><Fa icon="chevron-down" class="text-xs" /></button>
                    </div>
                    <input v-model="row.time" maxlength="40" :placeholder="t('admin.schedule.timePlaceholder')" :class="[input, border(err(`program.${i}.time`))]">
                    <input v-model="row.label.th" maxlength="255" :placeholder="t('admin.schedule.placeholderTh')" :class="[input, border(err(`program.${i}.label.th`))]">
                    <input v-model="row.label.en" maxlength="255" :placeholder="t('admin.schedule.placeholderEn')" :class="[input, border(err(`program.${i}.label.en`))]">
                    <button type="button" :class="iconBtn" class="hover:text-red-500!" :title="t('admin.schedule.remove')" @click="form.program.splice(i, 1)"><Fa icon="trash-can" class="text-xs" /></button>
                </li>
            </TransitionGroup>
            <button type="button" :class="addBtn" @click="form.program.push({ time: '', label: { th: '', en: '' } })"><Fa icon="plus" /> {{ t('admin.schedule.addItem') }}</button>
        </section>

        <p v-if="Object.keys(form.errors).length" class="text-sm text-red-500 dark:text-red-400"><Fa icon="circle-exclamation" /> {{ t('submissions.form.hasErrors') }}</p>
        <button
            type="submit"
            class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400"
            :disabled="form.processing || !form.isDirty"
        >
            <Fa icon="floppy-disk" /> {{ t('common.save') }}
        </button>
    </form>
</template>
