import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { i18n } from './i18n/index.js';

const appName = 'BudgetTracker - GUI CONNECT';

createInertiaApp({
    title: (title) => {
        if (!title || title === appName || title === 'Budget Tracker') {
            return appName;
        }

        return `${title} - ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Set initial locale from server-shared appSettings
        const lang = props.initialPage.props.appSettings?.language;
        if (lang) i18n.global.locale.value = lang;

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
