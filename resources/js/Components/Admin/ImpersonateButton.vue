<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';

// "View as this user" for admin (read only, see App\Support\Impersonation); hidden for everyone else
const props = defineProps({
    user: { type: Object, required: true }, // { id, name }
});

const page = usePage();
const { t } = useI18n();
const allowed = computed(() => page.props.auth.user.accessRole === 'admin' && !page.props.impersonation && page.props.auth.user.id !== props.user.id);

const asking = ref(false);
const busy = ref(false);
function start() {
    router.post(`/admin/impersonate/${props.user.id}`, {}, {
        onStart: () => (busy.value = true),
        onFinish: () => {
            busy.value = false;
            asking.value = false;
        },
    });
}
</script>

<template>
    <template v-if="allowed">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium transition hover:border-gold-400 hover:text-gold-600 dark:border-white/15 dark:hover:text-gold-400"
            @click="asking = true"
        >
            <Fa icon="user-secret" /> {{ t('impersonation.action') }}
        </button>
        <ConfirmModal
            :show="asking"
            icon="user-secret"
            :title="t('impersonation.confirmTitle')"
            :message="t('impersonation.confirmText', { name: user.name })"
            :confirm-label="t('impersonation.action')"
            :processing="busy"
            @close="asking = false"
            @confirm="start"
        />
    </template>
</template>
