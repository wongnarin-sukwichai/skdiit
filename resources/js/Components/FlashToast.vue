<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useToast } from '@/composables/useToast';

/*
 * Toasts, top right, stacked (user request 2026-11-11: a toast after every action). Shown for:
 *   - every action sent to the server (POST/PUT/DELETE): the server's flash message, or a plain "Done" when it sends none;
 *   - validation errors ("please check the form"), no permission (403), an expired page (419) or a lost connection;
 *   - client-side toasts (useToast), e.g. mock pages.
 * Page loads, filters and sign-in/out (GET, or the auth routes, which show their own modal) get no generic toast.
 * Soft cards (2026-11-11): a coloured strip and icon per kind (success green, danger red, warning amber, info blue),
 * a short heading, and a bar counting down to the close; hovering pauses it (the bar's animation ends the toast).
 */
const page = usePage();
const { t } = useI18n();
const { current } = useToast();

const toasts = ref([]); // { id, key, params, kind: success | danger | warning | info }
const duration = (kind) => (['danger', 'warning'].includes(kind) ? 6000 : 4000);
let seq = 0;

function show(key, params = {}, kind = 'success') {
    // The same message twice in a row: restart its timer instead of stacking a copy
    const same = toasts.value.find((x) => x.key === key && x.kind === kind);
    if (same) dismiss(same.id);
    const id = ++seq;
    toasts.value.push({ id, key, params, kind });
    if (toasts.value.length > 4) toasts.value.shift();
}
const dismiss = (id) => (toasts.value = toasts.value.filter((x) => x.id !== id));
// A server flash is an i18n key, or { key, params } (e.g. the welcome after signing in)
const showFlash = (value, kind = 'success') => (typeof value === 'object' ? show(value.key, value.params ?? {}, kind) : show(value, {}, kind));

// Routes whose result is shown by their own modal or a full page change
const QUIET = ['/login', '/logout', '/register', '/password/resend'];
const quiet = (url) => QUIET.some((path) => url?.pathname?.endsWith(path)) || /\/admin\/impersonate/.test(url?.pathname ?? '');

let action = null; // the non-GET visit in flight: { url }
const off = [];
onMounted(() => {
    // A flash already on the first page (e.g. after a full reload)
    if (page.props.flash?.success) showFlash(page.props.flash.success);
    if (page.props.flash?.error) showFlash(page.props.flash.error, 'danger');

    off.push(router.on('start', (event) => {
        const { method, url } = event.detail.visit;
        action = method !== 'get' ? { url } : null;
    }));
    off.push(router.on('success', (event) => {
        const flash = event.detail.page.props.flash ?? {};
        if (flash.error) showFlash(flash.error, 'danger');
        if (flash.success) showFlash(flash.success);
        else if (action && !flash.error && !quiet(action.url) && !flash.passwordSent && !flash.generatedPassword) show('common.toast.done');
        action = null;
    }));
    off.push(router.on('error', () => {
        if (action && !quiet(action.url)) show('common.toast.invalid', {}, 'warning');
        action = null;
    }));
    off.push(router.on('httpException', (event) => {
        const status = event.detail.response?.status;
        // No permission / expired page: a toast instead of the error page; other errors keep Inertia's error view
        if (status === 403 || status === 419) {
            event.preventDefault();
            show(status === 403 ? 'common.toast.forbidden' : 'common.toast.expired', {}, status === 403 ? 'danger' : 'warning');
        } else {
            show('common.toast.failed', {}, 'danger');
        }
        action = null;
    }));
    off.push(router.on('networkError', () => {
        show('common.toast.offline', {}, 'danger');
        action = null;
    }));
});
onBeforeUnmount(() => off.forEach((stop) => stop()));

watch(current, (value) => value && show(value.key, value.params, value.kind));

const styles = {
    success: { icon: 'circle-check', strip: 'bg-emerald-500', badge: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' },
    danger: { icon: 'circle-exclamation', strip: 'bg-red-500', badge: 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400' },
    warning: { icon: 'triangle-exclamation', strip: 'bg-amber-400', badge: 'bg-amber-100 text-amber-600 dark:bg-amber-400/15 dark:text-amber-300' },
    info: { icon: 'circle-info', strip: 'bg-sky-500', badge: 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400' },
};
</script>

<template>
    <div class="pointer-events-none fixed top-20 right-4 z-60 flex w-88 max-w-[calc(100vw-2rem)] flex-col items-end gap-3" aria-live="polite">
        <TransitionGroup name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="toast pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-xl border border-neutral-200/80 bg-white py-3.5 pr-3 pl-4 text-sm shadow-[0_10px_30px_-8px_rgba(0,0,0,.25)] dark:border-white/10 dark:bg-ink-800"
                :role="['danger', 'warning'].includes(toast.kind) ? 'alert' : 'status'"
            >
                <span :class="styles[toast.kind].strip" class="absolute inset-y-0 left-0 w-1" aria-hidden="true" />
                <span :class="styles[toast.kind].badge" class="grid size-8 shrink-0 place-items-center rounded-full">
                    <Fa :icon="styles[toast.kind].icon" class="text-sm" />
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p class="font-semibold text-neutral-900 dark:text-white">{{ t(`common.toast.kind.${toast.kind}`) }}</p>
                    <p class="mt-0.5 leading-snug text-neutral-600 dark:text-neutral-300">{{ t(toast.key, toast.params) }}</p>
                </div>
                <button type="button" class="grid size-6 shrink-0 place-items-center rounded-md text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-white/10 dark:hover:text-white" :aria-label="t('common.close')" @click="dismiss(toast.id)">
                    <Fa icon="xmark" class="text-xs" />
                </button>
                <!-- Time left; the toast closes when this bar runs out (paused while hovered) -->
                <span
                    :class="styles[toast.kind].strip"
                    class="toast-timer absolute bottom-0 left-0 h-0.5 w-full origin-left opacity-60"
                    :style="{ animationDuration: `${duration(toast.kind)}ms` }"
                    aria-hidden="true"
                    @animationend="dismiss(toast.id)"
                />
            </div>
        </TransitionGroup>
    </div>
</template>
