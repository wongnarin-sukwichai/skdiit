<script setup>
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import AccountsTable from '@/Components/Admin/Accounts/AccountsTable.vue';
import { useI18n } from '@/i18n';

// Staff accounts (admin only): admin, editor, journal
defineProps({
    accounts: { type: Object, required: true },
    filters: { type: Object, required: true },
    roles: { type: Array, required: true },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('admin.menu.staff')" />

    <Card class="space-y-6">
        <PageHeader icon="user-tie" :title="t('admin.menu.staff')" :subtitle="t('admin.staff.subtitle')" />
        <AccountsTable :accounts="accounts" :filters="filters" :roles="roles" base-url="/admin/staff" :add-label="t('admin.staff.add')" />
    </Card>
</template>
