<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import RegistrantStatus from '@/Components/Admin/Registrants/RegistrantStatus.vue';
import PaymentStatusBadge from '@/Components/PaymentStatusBadge.vue';
import { useI18n } from '@/i18n';
import { affiliationByKey } from '@/data/mock';

// Backend registrant record (mock: adminRegistrants)
const props = defineProps({
    registrant: { type: Object, default: null },
    seesPapers: { type: Boolean, default: true }, // false for finance: payment status only, no paper
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
                    <dt class="text-neutral-400">{{ t('admin.accounts.phone') }}</dt>
                    <dd class="mt-0.5">
                        <a v-if="shown.phone" :href="`tel:${shown.phone}`" class="text-gold-400 hover:underline">{{ shown.phone }}</a>
                        <span v-else class="text-neutral-500">—</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.affiliation') }}</dt>
                    <dd class="mt-0.5">{{ tr(affiliationByKey[shown.affiliation]) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.participation') }}</dt>
                    <dd class="mt-0.5">{{ t(`admin.registrants.participationType.${shown.participation}`) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.registeredAt') }}</dt>
                    <dd class="mt-0.5">{{ formatDate(shown.registeredAt, { dateStyle: 'medium', timeStyle: 'short' }) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t(seesPapers ? 'registrants.status' : 'registrants.paymentStatus') }}</dt>
                    <dd class="mt-1">
                        <RegistrantStatus v-if="seesPapers" :registrant="shown" />
                        <PaymentStatusBadge v-else :status="shown.payment" />
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('admin.registrants.account') }}</dt>
                    <dd v-if="!shown.active" class="mt-0.5 text-red-400">{{ t('admin.registrants.suspended') }}</dd>
                    <dd v-else-if="!shown.verified" class="mt-0.5 text-amber-400">
                        {{ t('admin.registrants.unverified') }}
                        <span class="block text-xs text-neutral-500">{{ t('admin.registrants.unverifiedHint') }}</span>
                    </dd>
                    <dd v-else class="mt-0.5 text-emerald-400">{{ t('admin.registrants.active') }}</dd>
                </div>
                <div v-if="seesPapers && shown.participation === 'attend_submit'" class="sm:col-span-2">
                    <dt class="text-neutral-400">{{ t('registrants.paper') }}</dt>
                    <dd class="mt-0.5" :class="{ 'text-neutral-500 italic': !shown.paper }">
                        {{ shown.paper ? tr(shown.paper) : t('registrants.noPaper') }}
                    </dd>
                </div>
            </dl>
        </div>
    </Modal>
</template>
