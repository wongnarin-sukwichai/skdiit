<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/Front/PageHeader.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';

// Contact details from Settings (Conference: email, phone; Website content: lead, address, hours)
const { t, tr } = useI18n();
const site = useSite();

const items = computed(() => [
    { icon: 'location-dot', value: tr(site.value.contact.address) },
    { icon: 'envelope', value: site.value.conference.email, href: `mailto:${site.value.conference.email}` },
    { icon: 'phone', value: site.value.conference.phone, href: `tel:${site.value.conference.phone.replace(/[^\d+]/g, '')}` },
    { icon: 'clock', value: tr(site.value.contact.hours) },
]);
</script>

<template>
    <Head :title="t('nav.contact')" />
    <PageHeader icon="envelope" :title="t('contact.title')" :lead="tr(site.contact.lead)" />

    <section class="mx-auto grid max-w-[1600px] gap-4 px-4 pb-12 sm:grid-cols-2 sm:px-6 lg:px-8">
        <component
            :is="item.href ? 'a' : 'div'"
            v-for="item in items"
            :key="item.icon"
            :href="item.href"
            class="flex items-start gap-4 rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6 transition hover:border-gold-400/50"
        >
            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-gold-400 text-lg text-black">
                <Fa :icon="item.icon" />
            </span>
            <span class="pt-2.5 text-neutral-100">{{ item.value }}</span>
        </component>
    </section>
</template>
