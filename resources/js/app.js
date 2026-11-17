import { createApp, h } from 'vue';
import { createInertiaApp, http } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@/plugins/fontawesome';
import { i18n } from '@/i18n';
import FrontLayout from '@/Layouts/FrontLayout.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { url, urlAbsolute } from '@/utils/url';

// Served under a sub-path (e.g. /skdiit)? Every Inertia visit and fetch() in the code uses root paths
// like '/admin/...'; add the prefix here once instead of at each call.
http.onRequest((config) => ({ ...config, url: urlAbsolute(config.url) }));
const nativeFetch = window.fetch.bind(window);
window.fetch = (input, init) => nativeFetch(typeof input === 'string' ? url(input) : input, init);

const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} · SKDIIT 2027` : 'SKDIIT 2027'),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];
        if (!page) throw new Error(`Page not found: ${name}`);

        // Persistent layout per area keeps header/sidebar mounted so only content fades
        page.default.layout ??= name.startsWith('Admin/') ? AdminLayout : FrontLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.globalProperties.$url = url; // templates: :href="$url('/admin')"
        app.use(plugin)
            .use(i18n)
            .component('Fa', FontAwesomeIcon)
            .mount(el);
    },
    progress: { color: '#ffcc29' },
});
