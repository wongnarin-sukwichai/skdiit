<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import { useI18n } from '@/i18n';

defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'switch']);

const { t } = useI18n();
const form = useForm({ email: '', password: '', remember: false });

// Testing phase only
const demoAccounts = computed(() => usePage().props.demoAccounts ?? []);
function fillDemo(account) {
    form.email = account.email;
    form.password = account.password;
    form.clearErrors();
}

function submit() {
    form.post('/login', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
        onFinish: () => form.reset('password'),
    });
}

function close() {
    form.clearErrors();
    emit('close');
}
</script>

<template>
    <Modal :show="show" @close="close">
        <form class="p-6 sm:p-8" @submit.prevent="submit">
            <span class="mb-4 grid size-12 place-items-center rounded-xl bg-gold-400/15 text-lg text-gold-400">
                <Fa icon="right-to-bracket" />
            </span>
            <h2 class="text-2xl font-semibold">{{ t('auth.loginTitle') }}</h2>
            <p class="mt-1 mb-6 text-sm text-neutral-400">{{ t('auth.loginSubtitle') }}</p>

            <div class="space-y-4">
                <FormField :label="t('auth.email')" icon="envelope" :error="form.errors.email" v-slot="{ inputClass }">
                    <input v-model="form.email" type="email" autocomplete="email" :class="inputClass" placeholder="name@example.com" required>
                </FormField>
                <FormField :label="t('auth.password')" icon="lock" :error="form.errors.password" v-slot="{ inputClass }">
                    <input v-model="form.password" type="password" autocomplete="current-password" :class="inputClass" required>
                </FormField>
                <label class="flex items-center gap-2 text-sm text-neutral-300">
                    <input v-model="form.remember" type="checkbox" class="size-4 accent-gold-400">
                    {{ t('auth.remember') }}
                </label>
            </div>

            <button
                type="submit"
                class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-gold-400 py-2.5 font-semibold text-black transition hover:bg-gold-300 disabled:opacity-60"
                :disabled="form.processing"
            >
                <Fa icon="right-to-bracket" />
                {{ t('auth.login') }}
            </button>

            <!-- Testing phase only: demo logins (config/demo.php) -->
            <div v-if="demoAccounts.length" class="mt-5 rounded-lg border border-dashed border-gold-400/40 bg-gold-400/5 p-3">
                <p class="mb-2 flex items-center gap-2 text-xs font-semibold text-gold-400">
                    <Fa icon="flask" /> {{ t('auth.demoTitle') }}
                </p>
                <ul class="space-y-1 text-xs">
                    <li v-for="account in demoAccounts" :key="account.email">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left transition hover:bg-white/5"
                            :title="t('auth.demoFill')"
                            @click="fillDemo(account)"
                        >
                            <span class="w-24 shrink-0 text-neutral-400">{{ t(`admin.role.${account.role}`) }}</span>
                            <span class="min-w-0 flex-1 truncate text-neutral-200">{{ account.email }}</span>
                            <span class="text-neutral-500">{{ account.password }}</span>
                        </button>
                    </li>
                </ul>
            </div>

            <p class="mt-5 text-center text-sm text-neutral-400">
                {{ t('auth.noAccount') }}
                <button type="button" class="font-medium text-gold-400 hover:underline" @click="emit('switch')">
                    {{ t('auth.register') }}
                </button>
            </p>
        </form>
    </Modal>
</template>
