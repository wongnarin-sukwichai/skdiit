import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@/plugins/fontawesome';
import { i18n } from '@/i18n';
import FrontLayout from '@/Layouts/FrontLayout.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .component('Fa', FontAwesomeIcon)
            .mount(el);
    },
    progress: { color: '#ffcc29' },
});
