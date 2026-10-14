<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Card from '@/Components/Admin/Card.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import RoleBadge from '@/Components/Admin/RoleBadge.vue';
import { useI18n } from '@/i18n';
import { useAcademicTitles } from '@/composables/useAcademicTitles';
import { OTHER_AFFILIATION, useAffiliations } from '@/composables/useAffiliations';

// My account: every backend role edits their own details (Admin\AccountController)
const props = defineProps({
    account: { type: Object, required: true },
});

const { t, tr, formatDate } = useI18n();
const { titles, titleOf } = useAcademicTitles();

const profile = useForm({
    name: props.account.name,
    academic_title_id: props.account.academic_title_id ?? '',
    phone: props.account.phone ?? '',
    affiliation: props.account.affiliation ?? '',
    affiliation_other: props.account.affiliation_other ?? '',
});

function saveProfile() {
    profile
        .transform((data) => ({
            ...data,
            academic_title_id: data.academic_title_id || null,
            phone: data.phone || null,
            affiliation: props.account.isRegistrant ? data.affiliation : undefined,
            affiliation_other: props.account.isRegistrant && data.affiliation === OTHER_AFFILIATION ? data.affiliation_other : undefined,
        }))
        .put('/admin/account', { preserveScroll: true, onSuccess: () => profile.defaults() });
}

// Same endpoint as "My details" on the public site (AuthController::changePassword)
const password = useForm({ current_password: '', password: '', password_confirmation: '' });
function savePassword() {
    password.put('/password', {
        preserveScroll: true,
        onSuccess: () => password.reset(),
        onError: () => password.reset(),
    });
}

const errorText = (error) => (error && /^(validation|auth)\./.test(error) ? t(error) : error);

const label = 'mb-1.5 block text-sm font-medium';
const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2.5 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const errorClass = 'mt-1 block text-xs text-red-500 dark:text-red-400';
</script>

<template>
    <Head :title="t('admin.menu.account')" />

    <div class="space-y-6">
        <Card class="space-y-6">
            <PageHeader icon="user-pen" :title="t('admin.menu.account')" :subtitle="t('admin.account.subtitle')" />

            <!-- Summary -->
            <div class="flex flex-col gap-5 rounded-xl border border-neutral-200 bg-neutral-50 p-5 sm:flex-row sm:items-center dark:border-white/10 dark:bg-white/5">
                <span class="grid size-16 shrink-0 place-items-center rounded-full bg-gold-100 text-2xl text-gold-600 dark:bg-gold-400/15 dark:text-gold-400">
                    <Fa icon="user" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xl font-semibold">
                        <span v-if="account.academic_title_id" class="text-neutral-500 dark:text-neutral-400">{{ titleOf(account.academic_title_id) }}</span>
                        {{ account.name }}
                    </p>
                    <p class="mt-0.5 break-all text-neutral-500 dark:text-neutral-400">{{ account.email }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <RoleBadge :role="account.role" />
                        <span
                            :class="account.emailVerified
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300'"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5"
                        >
                            <Fa :icon="account.emailVerified ? 'circle-check' : 'envelope'" />
                            {{ account.emailVerified ? t('admin.account.emailConfirmed') : t('admin.registrants.unverified') }}
                        </span>
                        <span v-if="account.createdAt" class="text-neutral-500 dark:text-neutral-400">
                            {{ t('admin.account.since', { date: formatDate(account.createdAt) }) }}
                        </span>
                    </div>
                </div>
            </div>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Profile -->
            <Card>
                <form class="space-y-4" @submit.prevent="saveProfile">
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        <Fa icon="id-card" class="text-gold-500" /> {{ t('admin.account.profile') }}
                    </h2>

                    <div>
                        <label for="acc-name" :class="label">{{ t('admin.accounts.name') }}</label>
                        <input id="acc-name" v-model="profile.name" :class="[input, border(profile.errors.name)]" required>
                        <span v-if="profile.errors.name" :class="errorClass">{{ errorText(profile.errors.name) }}</span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="acc-title" :class="label">{{ t('admin.accounts.academicTitle') }}</label>
                            <select id="acc-title" v-model="profile.academic_title_id" :class="[input, border(profile.errors.academic_title_id)]">
                                <option value="">{{ t('admin.accounts.noTitle') }}</option>
                                <option v-for="title in titles" :key="title.id" :value="title.id">{{ tr(title.short) }} ({{ tr(title.name) }})</option>
                            </select>
                        </div>
                        <div>
                            <label for="acc-phone" :class="label">{{ t('admin.accounts.phone') }}</label>
                            <input id="acc-phone" v-model="profile.phone" type="tel" :placeholder="t('admin.accounts.optional')" :class="[input, border(profile.errors.phone)]">
                            <span v-if="profile.errors.phone" :class="errorClass">{{ errorText(profile.errors.phone) }}</span>
                        </div>
                    </div>

                    <div v-if="account.isRegistrant">
                        <label for="acc-affiliation" :class="label">{{ t('auth.affiliation') }}</label>
                        <select id="acc-affiliation" v-model="profile.affiliation" :class="[input, border(profile.errors.affiliation)]">
                            <option value="" disabled>{{ t('auth.selectAffiliation') }}</option>
                            <option v-for="a in affiliations" :key="a.code" :value="a.code">{{ tr(a.name) }}</option>
                            <option :value="OTHER_AFFILIATION">{{ t('auth.affiliationOther') }}</option>
                        </select>
                        <span v-if="profile.errors.affiliation" :class="errorClass">{{ errorText(profile.errors.affiliation) }}</span>
                        <Transition name="fade">
                            <div v-if="profile.affiliation === OTHER_AFFILIATION" class="mt-3">
                                <input v-model="profile.affiliation_other" :placeholder="t('auth.affiliationOtherPlaceholder')" :aria-label="t('auth.affiliationOtherName')" :class="[input, border(profile.errors.affiliation_other)]">
                                <span v-if="profile.errors.affiliation_other" :class="errorClass">{{ errorText(profile.errors.affiliation_other) }}</span>
                            </div>
                        </Transition>
                    </div>

                    <div>
                        <span :class="label">{{ t('auth.email') }}</span>
                        <p class="rounded-lg border border-neutral-200 bg-neutral-100 px-3 py-2.5 text-sm break-all text-neutral-500 dark:border-white/10 dark:bg-white/5 dark:text-neutral-400">
                            <Fa icon="lock" class="mr-1.5 text-xs" /> {{ account.email }}
                        </p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.account.emailLocked') }}</p>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-50"
                            :disabled="profile.processing || !profile.isDirty"
                        >
                            <Fa icon="floppy-disk" /> {{ t('common.save') }}
                        </button>
                    </div>
                </form>
            </Card>

            <!-- Password -->
            <Card>
                <form class="space-y-4" @submit.prevent="savePassword">
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        <Fa icon="key" class="text-gold-500" /> {{ t('auth.changePassword') }}
                    </h2>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.account.passwordIntro') }}</p>

                    <div>
                        <label for="acc-current" :class="label">{{ t('auth.currentPassword') }}</label>
                        <input id="acc-current" v-model="password.current_password" type="password" autocomplete="current-password" :class="[input, border(password.errors.current_password)]" required>
                        <span v-if="password.errors.current_password" :class="errorClass">{{ errorText(password.errors.current_password) }}</span>
                    </div>
                    <div>
                        <label for="acc-new" :class="label">{{ t('auth.newPassword') }}</label>
                        <input id="acc-new" v-model="password.password" type="password" autocomplete="new-password" :class="[input, border(password.errors.password)]" required>
                        <span v-if="password.errors.password" :class="errorClass">{{ errorText(password.errors.password) }}</span>
                    </div>
                    <div>
                        <label for="acc-confirm" :class="label">{{ t('auth.passwordConfirm') }}</label>
                        <input id="acc-confirm" v-model="password.password_confirmation" type="password" autocomplete="new-password" :class="[input, border()]" required>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-50"
                            :disabled="password.processing"
                        >
                            <Fa icon="key" /> {{ t('auth.changePassword') }}
                        </button>
                    </div>
                </form>
            </Card>
        </div>
    </div>
</template>
