<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import { useI18n } from '@/i18n';
import { useAcademicTitles } from '@/composables/useAcademicTitles';

// Create or edit a staff / reviewer account (Admin\Concerns\ManagesAccounts)
const props = defineProps({
    show: { type: Boolean, default: false },
    account: { type: Object, default: null }, // null = create
    roles: { type: Array, required: true }, // one role = no picker
    baseUrl: { type: String, required: true },
});
const emit = defineEmits(['close']);

const { t, tr } = useI18n();
const { titles } = useAcademicTitles();
const form = useForm({ name: '', academic_title_id: '', email: '', phone: '', role: '', password: '' });

const isCreate = computed(() => !props.account);
const multiRole = computed(() => props.roles.length > 1);

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    form.defaults({
        name: props.account?.name ?? '',
        academic_title_id: props.account?.academic_title_id ?? '',
        email: props.account?.email ?? '',
        phone: props.account?.phone ?? '',
        role: props.account?.role ?? props.roles[0],
        password: '',
    });
    form.reset();
});

function submit() {
    const options = { preserveScroll: true, preserveState: true, onSuccess: () => emit('close') };
    const request = form.transform((data) => ({
        ...data,
        academic_title_id: data.academic_title_id || null,
        phone: data.phone || null,
        role: multiRole.value ? data.role : undefined,
    }));
    isCreate.value
        ? request.post(props.baseUrl, options)
        : request.put(`${props.baseUrl}/${props.account.id}`, options);
}
</script>

<template>
    <Modal :show="show" max-width="max-w-lg" @close="emit('close')">
        <form class="p-6 sm:p-8" @submit.prevent="submit">
            <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                <Fa :icon="isCreate ? 'user-plus' : 'user-pen'" />
            </span>
            <h2 class="mb-6 text-2xl font-semibold">{{ isCreate ? t('admin.accounts.addTitle') : t('admin.accounts.editTitle') }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField class="sm:col-span-2" :label="t('admin.accounts.name')" icon="user" :error="form.errors.name" v-slot="{ inputClass }">
                    <input v-model="form.name" :class="inputClass" required>
                </FormField>
                <FormField :label="t('admin.accounts.academicTitle')" icon="user-tie" :error="form.errors.academic_title_id" v-slot="{ inputClass }">
                    <select v-model="form.academic_title_id" :class="inputClass">
                        <option value="">{{ t('admin.accounts.noTitle') }}</option>
                        <option v-for="title in titles" :key="title.id" :value="title.id">{{ tr(title.short) }} ({{ tr(title.name) }})</option>
                    </select>
                </FormField>
                <FormField :label="t('admin.accounts.phone')" icon="phone" :error="form.errors.phone" v-slot="{ inputClass }">
                    <input v-model="form.phone" type="tel" autocomplete="off" :class="inputClass" :placeholder="t('admin.accounts.optional')">
                </FormField>
                <FormField class="sm:col-span-2" :label="t('auth.email')" icon="envelope" :error="form.errors.email" v-slot="{ inputClass }">
                    <input v-model="form.email" type="email" autocomplete="off" :class="inputClass" required>
                </FormField>
                <FormField v-if="multiRole" class="sm:col-span-2" :label="t('admin.accounts.role')" icon="user-tie" :error="form.errors.role" v-slot="{ inputClass }">
                    <select v-model="form.role" :class="inputClass">
                        <option v-for="r in roles" :key="r" :value="r">{{ t(`admin.role.${r}`) }}</option>
                    </select>
                </FormField>
                <FormField class="sm:col-span-2" :label="t('auth.password')" icon="lock" :error="form.errors.password" v-slot="{ inputClass }">
                    <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass" :required="isCreate">
                </FormField>
                <p v-if="!isCreate" class="-mt-2 text-xs text-neutral-500 sm:col-span-2">{{ t('admin.accounts.passwordHint') }}</p>
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
