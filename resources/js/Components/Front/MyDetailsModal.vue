<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import { useI18n } from '@/i18n';
import { useAffiliations } from '@/composables/useAffiliations';

const props = defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const { t, tr, formatDate } = useI18n();

// Only the signed-in account's own record (auth.user); nobody else's details are ever sent
const user = computed(() => usePage().props.auth.user);
const { affiliationOf } = useAffiliations();
const affiliation = computed(() => affiliationOf(user.value));

// Replace the emailed 6-digit password with one of their own (config/registration.php)
const changing = ref(false);
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
watch(() => props.show, () => {
    changing.value = false;
    passwordForm.reset();
    passwordForm.clearErrors();
});

function changePassword() {
    passwordForm.put('/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            changing.value = false;
        },
        onError: () => passwordForm.reset('current_password', 'password', 'password_confirmation'),
    });
}
</script>

<template>
    <Modal :show="show && !!user" max-width="max-w-lg" @close="emit('close')">
        <div v-if="user" class="p-6 sm:p-8">
            <div class="mb-6 flex items-center gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-full bg-gold-400/15 text-xl text-gold-400">
                    <Fa icon="user" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs tracking-wider text-gold-400 uppercase">{{ t('auth.myDetails') }}</p>
                    <h3 class="truncate text-xl font-semibold">{{ user.name }}</h3>
                </div>
            </div>

            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.email') }}</dt>
                    <dd class="mt-0.5 break-all">{{ user.email }}</dd>
                </div>
                <div v-if="user.phone">
                    <dt class="text-neutral-400">{{ t('admin.accounts.phone') }}</dt>
                    <dd class="mt-0.5">{{ user.phone }}</dd>
                </div>
                <div v-if="affiliation">
                    <dt class="text-neutral-400">{{ t('registrants.affiliation') }}</dt>
                    <dd class="mt-0.5">{{ affiliation }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">{{ t('registrants.participation') }}</dt>
                    <dd class="mt-0.5">{{ t(`admin.role.${user.accessRole}`) }}</dd>
                </div>
                <div v-if="user.created_at">
                    <dt class="text-neutral-400">{{ t('registrants.registeredAt') }}</dt>
                    <dd class="mt-0.5">{{ formatDate(user.created_at, { dateStyle: 'medium', timeStyle: 'short' }) }}</dd>
                </div>
            </dl>

            <div class="mt-6 border-t border-white/10 pt-5">
                <button
                    v-if="!changing"
                    type="button"
                    class="flex items-center gap-2 rounded-lg border border-white/20 px-4 py-2 text-sm transition hover:border-gold-400"
                    @click="changing = true"
                >
                    <Fa icon="key" class="text-gold-400" /> {{ t('auth.changePassword') }}
                </button>
                <Transition name="fade">
                    <form v-if="changing" class="space-y-4" @submit.prevent="changePassword">
                        <FormField :label="t('auth.currentPassword')" icon="lock" :error="passwordForm.errors.current_password" v-slot="{ inputClass }">
                            <input v-model="passwordForm.current_password" type="password" autocomplete="current-password" :class="inputClass" required>
                        </FormField>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField :label="t('auth.newPassword')" icon="lock" :error="passwordForm.errors.password" v-slot="{ inputClass }">
                                <input v-model="passwordForm.password" type="password" autocomplete="new-password" :class="inputClass" required>
                            </FormField>
                            <FormField :label="t('auth.passwordConfirm')" icon="lock" v-slot="{ inputClass }">
                                <input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" :class="inputClass" required>
                            </FormField>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="changing = false">
                                {{ t('common.cancel') }}
                            </button>
                            <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="passwordForm.processing">
                                <Fa icon="floppy-disk" /> {{ t('common.save') }}
                            </button>
                        </div>
                    </form>
                </Transition>
            </div>
        </div>
    </Modal>
</template>
