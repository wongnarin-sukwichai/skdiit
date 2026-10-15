<script setup>
import { reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import BilingualInput from '@/Components/Admin/Settings/BilingualInput.vue';
import ConfirmModal from '@/Components/Admin/ConfirmModal.vue';
import { useI18n } from '@/i18n';
import { useSite } from '@/composables/useSite';
import { formatFileSize } from '@/utils/format';

/*
 * Public-site content (SiteContentController): the home headline, About (lead and objectives), Contact,
 * and the files on the Documents page. The conference name, date, venue, email and phone are in the Conference tab;
 * the topics on About are the tracks (Tracks menu).
 */
const props = defineProps({
    documents: { type: Array, required: true }, // { id, name: { en, th }, name_th, name_en, file_name, type, size, url }
    maxMb: { type: Number, required: true },
});

const { t, tr } = useI18n();
const site = useSite();
const options = { preserveScroll: true, preserveState: true };
const clone = (value) => JSON.parse(JSON.stringify(value));

// One form per page; each saves on its own
const forms = {
    home: useForm(clone(site.value.home)),
    about: useForm(clone(site.value.about)),
    contact: useForm(clone(site.value.contact)),
    privacy: useForm(clone(site.value.privacy)),
};
watch(() => site.value, (value) => {
    for (const page of Object.keys(forms)) {
        forms[page].defaults(clone(value[page]));
        forms[page].reset();
    }
});
function savePage(page) {
    forms[page].put(`/admin/settings/site/pages/${page}`, options);
}

// ---- Documents ----
const newDoc = useForm({ name_th: '', name_en: '', file: null });
const newPicker = ref(null);
function addDocument() {
    newDoc.post('/admin/settings/site/documents', {
        ...options,
        forceFormData: true,
        onSuccess: () => {
            newDoc.reset();
            if (newPicker.value) newPicker.value.value = '';
        },
    });
}

// Rename or replace a file (multipart POST)
const drafts = reactive({});
const errors = reactive({});
watch(() => props.documents, (docs) => docs.forEach((d) => (drafts[d.id] = { name_th: d.name_th, name_en: d.name_en, file: null })), { immediate: true });
const isDirty = (doc) => drafts[doc.id].name_th !== doc.name_th || drafts[doc.id].name_en !== doc.name_en || !!drafts[doc.id].file;
function saveDocument(doc) {
    router.post(`/admin/settings/site/documents/${doc.id}`, drafts[doc.id], {
        ...options,
        forceFormData: true,
        onSuccess: () => (errors[doc.id] = {}),
        onError: (e) => (errors[doc.id] = e),
    });
}
function move(doc, direction) {
    router.put(`/admin/settings/site/documents/${doc.id}/move`, { direction }, options);
}
const removing = ref(null);
const shownRemoving = ref(null);
watch(removing, (value) => value && (shownRemoving.value = value));
function destroy() {
    router.delete(`/admin/settings/site/documents/${removing.value.id}`, { ...options, onFinish: () => (removing.value = null) });
}

const input = 'w-full rounded-lg border bg-neutral-50 px-3 py-2 text-sm outline-none transition focus:border-gold-400 focus:ring-2 focus:ring-gold-400/20 dark:bg-ink-800';
const border = (error) => (error ? 'border-red-500/70' : 'border-neutral-300 dark:border-white/10');
const box = 'space-y-4 rounded-xl border border-neutral-200 p-4 dark:border-white/10';
const h3 = 'flex items-center gap-2 font-semibold';
const saveBtn = 'flex items-center gap-2 rounded-lg bg-gold-400 px-5 py-2.5 text-sm font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40 disabled:hover:bg-gold-400';
const iconBtn = 'grid size-8 place-items-center rounded-md text-neutral-500 transition hover:bg-neutral-100 hover:text-gold-500 disabled:opacity-30 disabled:hover:bg-transparent dark:text-neutral-400 dark:hover:bg-white/10';
const accept = '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip';
</script>

<template>
    <div class="max-w-5xl space-y-6">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ t('admin.website.intro') }}</p>

        <!-- Home -->
        <form :class="box" @submit.prevent="savePage('home')">
            <h3 :class="h3"><Fa icon="house" class="text-gold-500" /> {{ t('admin.website.home') }}</h3>
            <BilingualInput v-model="forms.home.title" field="title" :label="t('admin.website.homeTitle')" :errors="forms.home.errors" />
            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.website.homeHint') }}</p>
            <button type="submit" :class="saveBtn" :disabled="forms.home.processing || !forms.home.isDirty"><Fa icon="floppy-disk" /> {{ t('common.save') }}</button>
        </form>

        <!-- About -->
        <form :class="box" @submit.prevent="savePage('about')">
            <h3 :class="h3"><Fa icon="building-columns" class="text-gold-500" /> {{ t('nav.about') }}</h3>
            <BilingualInput v-model="forms.about.lead" field="lead" multiline :maxlength="2000" :label="t('admin.website.lead')" :errors="forms.about.errors" />
            <div>
                <p class="mb-1.5 text-sm font-medium">{{ t('about.objectivesTitle') }}</p>
                <TransitionGroup name="fade" tag="ol" class="space-y-2">
                    <li v-for="(item, i) in forms.about.objectives" :key="i" class="flex gap-2">
                        <span class="mt-2 w-5 shrink-0 text-right text-sm text-neutral-400">{{ i + 1 }}.</span>
                        <div class="grid flex-1 gap-2 md:grid-cols-2">
                            <input v-model="item.th" maxlength="500" :placeholder="t('lang.th')" :class="[input, border(forms.about.errors[`objectives.${i}.th`])]">
                            <input v-model="item.en" maxlength="500" :placeholder="t('lang.en')" :class="[input, border(forms.about.errors[`objectives.${i}.en`])]">
                        </div>
                        <button type="button" :class="iconBtn" class="mt-0.5 hover:text-red-500!" :title="t('admin.website.removeItem')" @click="forms.about.objectives.splice(i, 1)"><Fa icon="trash-can" class="text-xs" /></button>
                    </li>
                </TransitionGroup>
                <button type="button" class="mt-2 flex items-center gap-2 rounded-lg border border-dashed border-gold-400 px-3 py-2 text-xs text-gold-600 transition hover:bg-gold-50 dark:text-gold-400 dark:hover:bg-gold-400/10" @click="forms.about.objectives.push({ th: '', en: '' })">
                    <Fa icon="plus" /> {{ t('admin.website.addObjective') }}
                </button>
            </div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.website.topicsHint') }}</p>
            <button type="submit" :class="saveBtn" :disabled="forms.about.processing || !forms.about.isDirty"><Fa icon="floppy-disk" /> {{ t('common.save') }}</button>
        </form>

        <!-- Contact -->
        <form :class="box" @submit.prevent="savePage('contact')">
            <h3 :class="h3"><Fa icon="envelope" class="text-gold-500" /> {{ t('nav.contact') }}</h3>
            <BilingualInput v-model="forms.contact.lead" field="lead" multiline :maxlength="2000" :label="t('admin.website.lead')" :errors="forms.contact.errors" />
            <BilingualInput v-model="forms.contact.address" field="address" multiline :maxlength="500" :label="t('admin.website.address')" :errors="forms.contact.errors" />
            <BilingualInput v-model="forms.contact.hours" field="hours" :label="t('admin.website.hours')" :errors="forms.contact.errors" />
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.website.contactHint') }}</p>
            <button type="submit" :class="saveBtn" :disabled="forms.contact.processing || !forms.contact.isDirty"><Fa icon="floppy-disk" /> {{ t('common.save') }}</button>
        </form>

        <!-- Privacy policy (PDPA): registrants consent to it at sign-up -->
        <form :class="box" @submit.prevent="savePage('privacy')">
            <h3 :class="h3"><Fa icon="lock" class="text-gold-500" /> {{ t('privacy.title') }}</h3>
            <BilingualInput v-model="forms.privacy.body" field="body" multiline :maxlength="20000" :label="t('admin.website.privacyBody')" :errors="forms.privacy.errors" />
            <p class="text-xs text-neutral-500 dark:text-neutral-400"><Fa icon="circle-info" class="mr-1 text-gold-500" /> {{ t('admin.website.privacyHint') }}</p>
            <button type="submit" :class="saveBtn" :disabled="forms.privacy.processing || !forms.privacy.isDirty"><Fa icon="floppy-disk" /> {{ t('common.save') }}</button>
        </form>

        <!-- Documents -->
        <section :class="box">
            <h3 :class="h3"><Fa icon="file-lines" class="text-gold-500" /> {{ t('important.title') }}</h3>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ t('admin.website.documentsHint', { max: maxMb }) }}</p>
            <ul class="divide-y divide-neutral-200 rounded-lg border border-neutral-200 dark:divide-white/5 dark:border-white/10">
                <li v-for="(doc, i) in documents" :key="doc.id" class="flex flex-wrap items-center gap-2 p-3">
                    <div class="flex">
                        <button type="button" :class="iconBtn" :disabled="i === 0" :title="t('admin.academicTitles.moveUp')" @click="move(doc, 'up')"><Fa icon="chevron-up" class="text-xs" /></button>
                        <button type="button" :class="iconBtn" :disabled="i === documents.length - 1" :title="t('admin.academicTitles.moveDown')" @click="move(doc, 'down')"><Fa icon="chevron-down" class="text-xs" /></button>
                    </div>
                    <div class="grid min-w-60 flex-1 gap-2 md:grid-cols-2">
                        <input v-model="drafts[doc.id].name_th" :placeholder="t('lang.th')" :class="[input, border(errors[doc.id]?.name_th)]">
                        <input v-model="drafts[doc.id].name_en" :placeholder="t('lang.en')" :class="[input, border(errors[doc.id]?.name_en)]">
                        <a :href="doc.url" class="truncate text-xs text-gold-600 hover:underline dark:text-gold-400 md:col-span-2"><Fa icon="download" class="mr-1" />{{ doc.file_name }} · {{ formatFileSize(doc.size) }}</a>
                        <span v-if="errors[doc.id]?.file" class="text-xs text-red-500 md:col-span-2">{{ t(errors[doc.id].file, { max: maxMb }) }}</span>
                    </div>
                    <label class="cursor-pointer rounded-lg border border-neutral-300 px-3 py-2 text-xs transition hover:border-gold-400 dark:border-white/15" :title="t('admin.website.replaceFile')">
                        <Fa icon="file-arrow-up" class="mr-1" />{{ drafts[doc.id].file ? drafts[doc.id].file.name : t('admin.website.replaceFile') }}
                        <input type="file" :accept="accept" class="sr-only" @change="drafts[doc.id].file = $event.target.files[0] ?? null">
                    </label>
                    <button type="button" class="flex items-center gap-1.5 rounded-lg bg-gold-400 px-3 py-2 text-xs font-semibold text-black transition hover:bg-gold-300 disabled:opacity-40" :disabled="!isDirty(doc)" @click="saveDocument(doc)">
                        <Fa icon="floppy-disk" /> {{ t('common.save') }}
                    </button>
                    <button type="button" :class="iconBtn" class="hover:text-red-500!" :title="t('admin.website.deleteDocument')" @click="removing = doc"><Fa icon="trash-can" class="text-xs" /></button>
                </li>
                <li v-if="!documents.length" class="p-6 text-center text-sm text-neutral-500 dark:text-neutral-400">{{ t('important.empty') }}</li>
            </ul>

            <!-- Add a document -->
            <form class="grid gap-2 rounded-lg bg-gold-50/50 p-3 md:grid-cols-[1fr_1fr_auto_auto] dark:bg-gold-400/5" @submit.prevent="addDocument">
                <input v-model="newDoc.name_th" :placeholder="t('admin.website.docNameTh')" :class="[input, border(newDoc.errors.name_th)]">
                <input v-model="newDoc.name_en" :placeholder="t('admin.website.docNameEn')" :class="[input, border(newDoc.errors.name_en)]">
                <label class="flex cursor-pointer items-center gap-1.5 truncate rounded-lg border border-neutral-300 px-3 py-2 text-xs transition hover:border-gold-400 dark:border-white/15" :class="newDoc.errors.file ? 'border-red-500/70' : ''">
                    <Fa icon="paperclip" /> {{ newDoc.file ? newDoc.file.name : t('reviews.form.chooseFile') }}
                    <input ref="newPicker" type="file" :accept="accept" class="sr-only" @change="newDoc.file = $event.target.files[0] ?? null">
                </label>
                <button type="submit" class="flex items-center justify-center gap-1.5 rounded-lg border border-gold-400 px-3 py-2 text-xs font-semibold text-gold-600 transition hover:bg-gold-400 hover:text-black disabled:opacity-40 dark:text-gold-400" :disabled="newDoc.processing || !newDoc.name_th.trim() || !newDoc.name_en.trim() || !newDoc.file">
                    <Fa icon="plus" /> {{ t('admin.website.addDocument') }}
                </button>
                <span v-if="newDoc.errors.file" class="text-xs text-red-500 md:col-span-4">{{ t(newDoc.errors.file, { max: maxMb }) }}</span>
            </form>
        </section>
    </div>

    <ConfirmModal
        :show="!!removing"
        icon="trash-can"
        danger
        :title="t('admin.website.deleteDocument')"
        :message="shownRemoving ? t('admin.website.deleteText', { name: tr(shownRemoving.name) }) : ''"
        :confirm-label="t('admin.website.deleteDocument')"
        @close="removing = null"
        @confirm="destroy"
    />
</template>
