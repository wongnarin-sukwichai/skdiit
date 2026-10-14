<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { timeAgo, useNotifications } from '@/composables/useNotifications';

// Header bell: unread count and the latest notifications (the full list is the Notifications menu)
const open = defineModel('open', { type: Boolean, default: false });
defineProps({
    allHref: { type: String, default: '/admin/notifications' }, // reviewers: the home page
});

const { t, tr, formatNumber } = useI18n();
const { items, unread, markRead, markAllRead } = useNotifications();
const latest = computed(() => items.value.slice(0, 5));

function go(item) {
    markRead(item);
    open.value = false;
    router.visit(item.href);
}
</script>

<template>
    <div class="relative">
        <button
            class="relative grid size-10 place-items-center text-xl transition hover:text-gold-400"
            :aria-label="t('admin.notifications')"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Fa icon="bell" />
            <Transition name="fade">
                <span v-if="unread" class="absolute top-0.5 right-0 grid h-5 min-w-5 place-items-center rounded-full bg-gold-400 px-1 text-[0.65rem] font-bold text-black">
                    {{ unread > 9 ? '9+' : formatNumber(unread) }}
                </span>
            </Transition>
        </button>

        <Transition name="fade">
            <div v-if="open" class="fixed inset-x-3 top-20 mt-1 overflow-hidden rounded-xl border border-neutral-200 bg-white text-neutral-800 shadow-xl sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-3 sm:w-96 dark:border-white/10 dark:bg-ink-900 dark:text-neutral-100">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-white/10">
                    <p class="font-semibold">{{ t('admin.notificationsPage.title') }}</p>
                    <button v-if="unread" class="text-xs text-gold-600 hover:underline dark:text-gold-400" @click="markAllRead">{{ t('admin.notificationsPage.markAll') }}</button>
                </div>
                <ul class="max-h-96 divide-y divide-neutral-100 overflow-y-auto dark:divide-white/5">
                    <li v-for="item in latest" :key="item.id">
                        <button class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-neutral-50 dark:hover:bg-white/5" @click="go(item)">
                            <span :class="item.read ? 'bg-neutral-100 text-neutral-500 dark:bg-white/5 dark:text-neutral-400' : 'bg-gold-100 text-gold-600 dark:bg-gold-400/15 dark:text-gold-400'" class="grid size-9 shrink-0 place-items-center rounded-full text-sm">
                                <Fa :icon="item.icon" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span :class="item.read ? '' : 'font-semibold'" class="block text-sm">{{ tr(item.title) }}</span>
                                <span class="line-clamp-2 text-xs text-neutral-500 dark:text-neutral-400">{{ tr(item.body) }}</span>
                                <span class="mt-0.5 block text-xs text-neutral-400">{{ timeAgo(item.at) }}</span>
                            </span>
                            <span v-if="!item.read" class="mt-1.5 size-2 shrink-0 rounded-full bg-gold-400" />
                        </button>
                    </li>
                    <li v-if="!latest.length" class="px-4 py-10 text-center text-sm text-neutral-500">{{ t('admin.notificationsPage.empty') }}</li>
                </ul>
                <Link :href="allHref" class="block border-t border-neutral-200 px-4 py-2.5 text-center text-sm font-medium text-gold-600 hover:bg-neutral-50 dark:border-white/10 dark:text-gold-400 dark:hover:bg-white/5" @click="open = false">
                    {{ t('admin.notificationsPage.viewAll') }}
                </Link>
            </div>
        </Transition>
    </div>
</template>
