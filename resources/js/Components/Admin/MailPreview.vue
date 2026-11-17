<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useModules } from '@/composables/useModules';

/*
 * One email exactly as it will go out (2026-11-13): recipient, subject, how it is sent, then the text in the email's
 * own look (Thai, the button, English), and a link to its template. Used wherever an editor confirms an email:
 * "Send reminder", "Close the revision", the group reminders. preview: { to: { name, email }, subject, bodyTh, bodyEn, template }
 */
defineProps({
    preview: { type: Object, required: true },
    recipients: { type: Number, default: 0 }, // group reminders: how many get their own copy (this is the first one)
});
const emit = defineEmits(['leave']); // the template link navigates away: the parent closes its dialog

const { t } = useI18n();
const { accessTo } = useModules();
</script>

<template>
    <div class="space-y-3">
        <dl class="grid grid-cols-[5.5rem_1fr] gap-x-3 gap-y-1 border-b border-white/10 pb-3 text-sm">
            <dt class="text-neutral-400">{{ t('admin.notificationsPage.to') }}</dt>
            <dd class="break-all">
                {{ preview.to.name }} &lt;{{ preview.to.email }}&gt;
                <span v-if="recipients > 1" class="ml-1 text-xs text-gold-400">{{ t('mailPreview.andOthers', { n: recipients - 1 }) }}</span>
            </dd>
            <dt class="text-neutral-400">{{ t('admin.notificationsPage.subject') }}</dt>
            <dd class="font-semibold">{{ preview.subject }}</dd>
            <dt class="text-neutral-400">{{ t('remindAuthor.channels') }}</dt>
            <dd><Fa icon="envelope" class="mr-1 text-gold-400" />{{ t('remindAuthor.channelEmail') }} · <Fa icon="bell" class="mr-1 text-gold-400" />{{ t('remindAuthor.channelBell') }}</dd>
        </dl>
        <p v-if="recipients > 1" class="text-xs text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-400" />{{ t('mailPreview.eachOwn') }}</p>
        <!-- Same look as the email itself: Thai first, the button, then English -->
        <div class="max-h-[40vh] overflow-y-auto rounded-2xl border border-gold-400/30 bg-[#161616] p-5 text-sm leading-relaxed whitespace-pre-line text-neutral-200">
            <p>{{ preview.bodyTh }}</p>
            <p class="my-4 text-center"><span class="inline-block rounded-lg bg-gold-400 px-5 py-2 font-semibold text-black">เข้าสู่ระบบ / Open the system</span></p>
            <hr class="my-4 border-white/10">
            <p class="text-neutral-400">{{ preview.bodyEn }}</p>
        </div>
        <p class="text-xs text-neutral-500">
            <Fa icon="circle-info" class="mr-1 text-gold-400" />{{ t('remindAuthor.templateHint') }}
            <Link
                v-if="accessTo('announcements') && preview.template"
                :href="$url(`/admin/announcements?tab=templates&template=${preview.template}`)"
                class="ml-1 inline-flex items-center gap-1 font-medium text-gold-400 underline-offset-2 hover:underline"
                @click="emit('leave')"
            >
                {{ t('remindAuthor.editTemplate') }} <Fa icon="arrow-up-right-from-square" class="text-[0.65rem]" />
            </Link>
        </p>
    </div>
</template>
