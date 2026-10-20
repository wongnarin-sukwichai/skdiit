<script setup>
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { journeySteps } from '@/utils/journey';

// The author's journey in one line for lists: a dot per step and the name of the step the paper is on
const props = defineProps({
    status: { type: String, required: true },
    editable: { type: Boolean, default: false }, // a new paper still inside the submission period
});

const { t } = useI18n();

const steps = computed(() => journeySteps({ status: props.status, editable: props.editable }));
const now = computed(() => steps.value.find((s) => ['current', 'failed'].includes(s.state)) ?? steps.value[steps.value.length - 1]);

const dot = {
    done: 'size-2 bg-emerald-500',
    current: 'size-3 bg-gold-400 ring-3 ring-gold-400/30',
    upcoming: 'size-2 bg-neutral-300 dark:bg-white/20',
    failed: 'size-3 bg-red-500 ring-3 ring-red-500/25',
};
</script>

<template>
    <span class="inline-flex flex-col gap-1.5">
        <span class="flex items-center" :aria-hidden="true">
            <template v-for="(step, i) in steps" :key="step.key">
                <span v-if="i" :class="steps[i - 1].state === 'done' ? 'bg-emerald-500' : 'bg-neutral-200 dark:bg-white/10'" class="h-0.5 w-3" />
                <span :class="dot[step.state]" class="shrink-0 rounded-full" :title="t(`submissions.journey.step.${step.key}`)" />
            </template>
        </span>
        <span :class="now.state === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-neutral-700 dark:text-neutral-200'" class="text-xs font-medium whitespace-nowrap">
            {{ t(`submissions.journey.step.${now.key}`) }}<template v-if="now.state === 'failed'"> · {{ t('submissions.journey.note.notAccepted') }}</template>
        </span>
    </span>
</template>
