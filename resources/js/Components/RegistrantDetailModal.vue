<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useI18n } from '@/i18n';
import { affiliationByKey } from '@/data/mock';

const props = defineProps({
    registrant: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const { t, tr, formatDate } = useI18n();

// Keep the last record so the content stays visible while the modal fades out
const shown = ref(null);
watch(() => props.registrant, (value) => value && (shown.value = value), { immediate: true });
</script>

<template>
    <Modal :show="!!props.registrant" max-width="max-w-lg" @close="emit('close')">
        <div v-if="shown" class="p-6 sm:p-8">
            <div class="mb-6 flex items-center gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-full bg-gold-400/15 text-xl text-gold-400">
                    <Fa icon="user" />
                </span>
                <div>
                    <p class="text-xs tracking-wider text-gold-400 uppercase">{{ t('registrants.detailTitle') }}</p>
                    <h3 class="text-xl font-semibold">{{ tr(shown.name) }}</h3>
                </div>
            </div>

            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.email') }}</dt>
                    <dd class="mt-0.5 break-all">{{ shown.email }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.affiliation') }}</dt>
                    <dd class="mt-0.5">{{ tr(affiliationByKey[shown.affiliation]) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.participation') }}</dt>
                    <dd class="mt-0.5">{{ shown.submitted ? t('auth.attendSubmit') : t('auth.attendOnly') }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.registeredAt') }}</dt>
                    <dd class="mt-0.5">{{ formatDate(shown.registeredAt, { dateStyle: 'medium', timeStyle: 'short' }) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.status') }}</dt>
                    <dd class="mt-1"><StatusBadge :status="shown.status" /></dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-neutral-400">{{ t('registrants.paper') }}</dt>
                    <dd class="mt-0.5" :class="{ 'text-neutral-500 italic': !shown.paper }">
                        {{ shown.paper ? tr(shown.paper) : t('registrants.noPaper') }}
                    </dd>
                </div>
            </dl>
        </div>
    </Modal>
</template>
