<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import RoleBadge from '@/Components/Admin/RoleBadge.vue';
import UserFormModal from '@/Components/Admin/Settings/UserFormModal.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    users: { type: Object, required: true }, // Laravel paginator
    filters: { type: Object, required: true },
    staffRoles: { type: Array, required: true },
});

const { t, formatDate } = useI18n();
const me = computed(() => usePage().props.auth.user);

const q = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');
const roleOptions = computed(() => [...props.staffRoles, 'author', 'attendee']);

function load(params = {}) {
    router.get('/admin/settings', { q: q.value || undefined, role: role.value || undefined, ...params }, {
        only: ['users', 'filters'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
const page = computed({
    get: () => props.users.current_page,
    set: (value) => load({ page: value }),
});
function reset() {
    q.value = '';
    role.value = '';
    load();
}

// Create (null user) or edit
const editing = ref(null);
const formOpen = ref(false);
function openForm(user = null) {
    editing.value = user;
    formOpen.value = true;
}

const deleting = ref(null);
const deleteBusy = ref(false);
function destroy() {
    router.delete(`/admin/settings/users/${deleting.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (deleteBusy.value = true),
        onFinish: () => {
            deleteBusy.value = false;
            deleting.value = null;
        },
    });
}

const input = 'w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:border-white/10 dark:bg-ink-800';
const iconBtn = 'grid size-8 place-items-center rounded-md border border-neutral-300 text-neutral-600 transition hover:border-gold-400 hover:text-gold-500 disabled:opacity-40 disabled:hover:border-neutral-300 disabled:hover:text-neutral-600 dark:border-white/15 dark:text-neutral-200';
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form class="grid flex-1 gap-3 sm:grid-cols-[1fr_12rem_auto_auto]" @submit.prevent="load()">
                <label class="relative">
                    <Fa icon="magnifying-glass" class="absolute top-1/2 left-4 -translate-y-1/2 text-neutral-400" />
                    <input v-model="q" :placeholder="t('admin.settings.users.search')" :class="[input, 'pl-11']">
                </label>
                <select v-model="role" :class="input" @change="load()">
                    <option value="">{{ t('admin.settings.users.allRoles') }}</option>
                    <option v-for="r in roleOptions" :key="r" :value="r">{{ t(`admin.role.${r}`) }}</option>
                </select>
                <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300">
                    <Fa icon="magnifying-glass" /> {{ t('registrants.search') }}
                </button>
                <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2.5 text-sm transition hover:border-gold-400 dark:border-white/20" @click="reset">
                    <Fa icon="arrow-rotate-right" /> {{ t('registrants.reset') }}
                </button>
            </form>
            <button class="flex items-center gap-2 rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300" @click="openForm()">
                <Fa icon="user-plus" /> {{ t('admin.settings.users.add') }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-white/10">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-white/5 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('admin.settings.users.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('admin.settings.users.role') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('admin.settings.users.joined') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ t('registrants.actions') }}</span></th>
                    </tr>
                </thead>
                <Transition name="fade" mode="out-in">
                    <tbody :key="`${users.current_page}-${users.total}-${filters.q}-${filters.role}`" class="divide-y divide-neutral-200 dark:divide-white/5">
                        <tr v-for="user in users.data" :key="user.id" class="transition hover:bg-gold-50 dark:hover:bg-gold-400/5">
                            <td class="px-4 py-3">
                                <span class="block font-medium">
                                    {{ user.name }}
                                    <span v-if="user.id === me.id" class="ml-1 text-xs text-gold-600 dark:text-gold-400">({{ t('admin.settings.users.you') }})</span>
                                </span>
                                <span class="block text-neutral-500 dark:text-neutral-400">{{ user.email }}</span>
                            </td>
                            <td class="px-4 py-3"><RoleBadge :role="user.accessRole" /></td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500 dark:text-neutral-400">{{ user.createdAt ? formatDate(user.createdAt) : '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1.5">
                                    <button :class="iconBtn" :title="t('admin.settings.users.edit')" @click="openForm(user)"><Fa icon="pen" class="text-xs" /></button>
                                    <button :class="iconBtn" :title="t('admin.settings.users.delete')" :disabled="user.id === me.id" @click="deleting = user"><Fa icon="trash-can" class="text-xs" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td colspan="4" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.users.empty') }}</td>
                        </tr>
                    </tbody>
                </Transition>
            </table>
        </div>

        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.settings.users.total', { total: users.total }) }}</p>
            <Pagination v-if="users.last_page > 1" v-model="page" :last-page="users.last_page" />
        </div>

        <UserFormModal :show="formOpen" :user="editing" :staff-roles="staffRoles" @close="formOpen = false" />

        <Modal :show="!!deleting" @close="deleting = null">
            <div class="p-6 sm:p-8">
                <span class="mb-4 grid size-12 place-items-center rounded-xl bg-red-500/15 text-lg text-red-400">
                    <Fa icon="trash-can" />
                </span>
                <h2 class="text-xl font-semibold">{{ t('admin.settings.users.deleteTitle') }}</h2>
                <p class="mt-2 text-sm text-neutral-400">{{ t('admin.settings.users.deleteText', { name: deleting?.name ?? '' }) }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-lg border border-white/20 px-4 py-2.5 text-sm transition hover:border-gold-400" @click="deleting = null">{{ t('common.cancel') }}</button>
                    <button class="flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-400 disabled:opacity-60" :disabled="deleteBusy" @click="destroy">
                        <Fa icon="trash-can" /> {{ t('admin.settings.users.delete') }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
