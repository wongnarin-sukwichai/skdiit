<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import RoleBadge from '@/Components/Admin/RoleBadge.vue';
import { useI18n } from '@/i18n';
import { useAcademicTitles } from '@/composables/useAcademicTitles';

// Staff or reviewer account (Admin\Concerns\ManagesAccounts::accountRow)
const props = defineProps({
    account: { type: Object, default: null },
    trackNames: { type: Array, default: null }, // reviewers: names of their tracks
});
const emit = defineEmits(['close']);

const { t, formatDate } = useI18n();
const { titleOf } = useAcademicTitles();

// Keep the last record so the content stays visible while the modal fades out
const shown = ref(null);
watch(() => props.account, (value) => value && (shown.value = value), { immediate: true });
</script>

<template>
    <Modal :show="!!account" max-width="max-w-lg" @close="emit('close')">
        <div v-if="shown" class="p-6 sm:p-8">
            <div class="mb-6 flex items-center gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-full bg-gold-400/15 text-xl text-gold-400">
                    <Fa icon="user" />
                </span>
                <div class="min-w-0">
                    <RoleBadge :role="shown.role" />
                    <h3 class="mt-1 text-xl font-semibold">
                        <span v-if="shown.academic_title_id" class="text-neutral-400">{{ titleOf(shown.academic_title_id) }}</span>
                        {{ shown.name }}
                    </h3>
                </div>
            </div>

            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.email') }}</dt>
                    <dd class="mt-0.5 break-all">{{ shown.email }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('admin.accounts.phone') }}</dt>
                    <dd class="mt-0.5">{{ shown.phone || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('admin.registrants.account') }}</dt>
                    <dd v-if="shown.suspended" class="mt-0.5 text-red-400">{{ t('admin.registrants.suspended') }}</dd>
                    <dd v-else class="mt-0.5 text-emerald-400">{{ t('admin.registrants.active') }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('admin.accounts.emailStatus') }}</dt>
                    <dd class="mt-0.5" :class="shown.emailVerified ? 'text-emerald-400' : 'text-amber-400'">
                        {{ shown.emailVerified ? t('admin.accounts.emailConfirmed') : t('admin.registrants.unverified') }}
                    </dd>
                </div>
                <div v-if="trackNames" class="sm:col-span-2">
                    <dt class="text-neutral-400">{{ t('admin.accounts.tracks') }}</dt>
                    <dd v-if="trackNames.length" class="mt-1 flex flex-wrap gap-1.5">
                        <span v-for="name in trackNames" :key="name" class="rounded-full border border-gold-400/40 bg-gold-400/10 px-2.5 py-0.5 text-xs text-gold-300">{{ name }}</span>
                    </dd>
                    <dd v-else class="mt-0.5 text-red-400">{{ t('admin.accounts.noTrack') }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('admin.accounts.joined') }}</dt>
                    <dd class="mt-0.5">{{ shown.createdAt ? formatDate(shown.createdAt) : '—' }}</dd>
                </div>
            </dl>
        </div>
    </Modal>
</template>
