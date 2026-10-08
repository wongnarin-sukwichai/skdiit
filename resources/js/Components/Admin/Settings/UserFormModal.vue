<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null }, // null = create a staff account
    staffRoles: { type: Array, required: true },
});
const emit = defineEmits(['close']);

const { t } = useI18n();
const form = useForm({ name: '', email: '', role: 'editor', password: '' });

const isCreate = computed(() => !props.user);
// Registrants keep their role; only staff accounts have a role picker
const canPickRole = computed(() => isCreate.value || props.user.isStaff);

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    form.defaults({
        name: props.user?.name ?? '',
        email: props.user?.email ?? '',
        role: props.user?.isStaff ? props.user.role : 'editor',
        password: '',
    });
    form.reset();
});

function submit() {
    const options = { preserveScroll: true, preserveState: true, onSuccess: () => emit('close') };
    if (isCreate.value) {
        form.post('/admin/settings/users', options);
    } else {
        form.transform((data) => (canPickRole.value ? data : { ...data, role: undefined }))
            .put(`/admin/settings/users/${props.user.id}`, options);
    }
}
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <form class="p-6 sm:p-8" @submit.prevent="submit">
            <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                <Fa :icon="isCreate ? 'user-plus' : 'user-pen'" />
            </span>
            <h2 class="mb-6 text-2xl font-semibold">{{ isCreate ? t('admin.settings.users.add') : t('admin.settings.users.edit') }}</h2>

            <div class="space-y-4">
                <FormField :label="t('admin.settings.users.name')" icon="user" :error="form.errors.name" v-slot="{ inputClass }">
                    <input v-model="form.name" :class="inputClass" required>
                </FormField>
                <FormField :label="t('auth.email')" icon="envelope" :error="form.errors.email" v-slot="{ inputClass }">
                    <input v-model="form.email" type="email" autocomplete="off" :class="inputClass" required>
                </FormField>
                <FormField v-if="canPickRole" :label="t('admin.settings.users.role')" icon="user-tie" :error="form.errors.role" v-slot="{ inputClass }">
                    <select v-model="form.role" :class="inputClass">
                        <option v-for="r in staffRoles" :key="r" :value="r">{{ t(`admin.role.${r}`) }}</option>
                    </select>
                </FormField>
                <p v-else class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-xs text-neutral-400">
                    <Fa icon="circle-info" class="mr-1 text-gold-400" /> {{ t('admin.settings.users.registrantNote') }}
                </p>
                <FormField :label="t('auth.password')" icon="lock" :error="form.errors.password" v-slot="{ inputClass }">
                    <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass" :required="isCreate">
                </FormField>
                <p v-if="!isCreate" class="-mt-2 text-xs text-neutral-500">{{ t('admin.settings.users.passwordHint') }}</p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="submit" class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60" :disabled="form.processing">
                    <Fa icon="floppy-disk" /> {{ t('common.save') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
