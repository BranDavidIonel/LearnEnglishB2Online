import { createInertiaApp } from '@inertiajs/vue3';
import { i18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { learner } from '@/utils/learner';

const appName = import.meta.env.VITE_APP_NAME || 'English Levels';

void createInertiaApp({
    // "Words · English Levels"; a page without a title keeps the original "English Levels · A1 → B2".
    title: (title) =>
        title ? `${title} · ${appName}` : `${appName} · A1 → B2`,
    // The login page gets the centered layout, every other page the app shell.
    layout: (name: string) =>
        name.startsWith('auth/') ? AuthLayout : AppLayout,
    withApp: (app) => {
        app.use(i18n);
        app.use(learner);
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#6366f1',
    },
});
