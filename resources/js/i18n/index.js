import { ref } from 'vue';
import en from './en';
import th from './th';

const messages = { en, th };
const STORAGE_KEY = 'skdiit-locale';

function readSaved() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        return saved in messages ? saved : 'en';
    } catch {
        return 'en';
    }
}

// English is the primary language
export const locale = ref(readSaved());
document.documentElement.lang = locale.value;

export function setLocale(value) {
    locale.value = value;
    document.documentElement.lang = value;
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        /* storage unavailable: keep in memory only */
    }
}

function lookup(dict, key) {
    return key.split('.').reduce((node, part) => node?.[part], dict);
}

/** Translate a dotted key, replacing {placeholders} with params. Falls back to English, then the key. */
export function t(key, params = {}) {
    const value = lookup(messages[locale.value], key) ?? lookup(messages.en, key) ?? key;
    return typeof value === 'string'
        ? value.replace(/\{(\w+)\}/g, (_, name) => params[name] ?? `{${name}}`)
        : value;
}

/** Pick the active-language field from bilingual data like { en: '...', th: '...' }. */
export function tr(value) {
    if (value == null || typeof value !== 'object') return value;
    return value[locale.value] ?? value.en;
}

/** Format a date (string or Date) for the active language; Thai uses the Buddhist calendar. */
export function formatDate(value, options = { day: 'numeric', month: 'short', year: 'numeric' }) {
    const tag = locale.value === 'th' ? 'th-TH' : 'en-GB';
    return new Intl.DateTimeFormat(tag, options).format(new Date(value));
}

export function formatNumber(value) {
    return new Intl.NumberFormat(locale.value === 'th' ? 'th-TH' : 'en-US').format(value);
}

export function useI18n() {
    return { locale, setLocale, t, tr, formatDate, formatNumber };
}

export const i18n = {
    install(app) {
        app.config.globalProperties.$t = t;
        app.config.globalProperties.$tr = tr;
    },
};
