<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import RegistrantStatus from '@/Components/Admin/Registrants/RegistrantStatus.vue';
import { useI18n } from '@/i18n';
import { useAffiliations } from '@/composables/useAffiliations';
import { useMealOptions } from '@/composables/useMealOptions';

// A row of RegistrantController::index
const props = defineProps({
    registrant: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const { t, tr, formatDate } = useI18n();
const { affiliationOf } = useAffiliations();
const { mealOf, companionMealOf } = useMealOptions();

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
                    <h3 class="text-xl font-semibold">{{ shown.name }}</h3>
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
                    <dd class="mt-0.5">{{ affiliationOf(shown) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.participation') }}</dt>
                    <dd class="mt-0.5">{{ t(`admin.registrants.participationType.${shown.participation}`) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('meals.yourMeal') }}</dt>
                    <dd class="mt-0.5" :class="{ 'text-neutral-500': !shown.meal }">{{ mealOf(shown) || t('meals.notChosen') }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('meals.companion') }}</dt>
                    <dd v-if="shown.companion_name" class="mt-0.5">
                        {{ shown.companion_name }}
                        <span class="block text-xs text-neutral-400">{{ companionMealOf(shown) }}</span>
                    </dd>
                    <dd v-else class="mt-0.5 text-neutral-500">{{ t('meals.noCompanion') }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.registeredAt') }}</dt>
                    <dd class="mt-0.5">{{ formatDate(shown.registeredAt, { dateStyle: 'medium', timeStyle: 'short' }) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.status') }}</dt>
                    <dd class="mt-1">
                        <RegistrantStatus :registrant="shown" />
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('admin.registrants.account') }}</dt>
                    <dd v-if="shown.suspended" class="mt-0.5 text-red-400">{{ t('admin.registrants.suspended') }}</dd>
                    <dd v-else-if="!shown.verified" class="mt-0.5 text-amber-400">
                        {{ t('admin.registrants.unverified') }}
                        <span class="block text-xs text-neutral-500">{{ t('admin.registrants.unverifiedHint') }}</span>
                    </dd>
                    <dd v-else class="mt-0.5 text-emerald-400">{{ t('admin.registrants.active') }}</dd>
                </div>
                <div v-if="shown.participation === 'attend_submit'" class="sm:col-span-2">
                    <dt class="text-neutral-400">{{ t('registrants.paper') }}</dt>
                    <dd class="mt-0.5" :class="{ 'text-neutral-500 italic': !shown.paper }">
                        <template v-if="shown.paper"><span class="font-mono text-xs text-gold-400">{{ shown.paper.code }}</span> {{ tr(shown.paper.title) }}</template>
                        <template v-else>{{ t('registrants.noPaper') }}</template>
                    </dd>
                </div>
            </dl>
        </div>
    </Modal>
</template>
