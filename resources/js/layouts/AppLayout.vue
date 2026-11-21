<template>
    <SideNav />
    <div class="content">
        <TopBar @toggle-menu="menuOpen = !menuOpen" />
        <main id="main">
            <slot />
        </main>
    </div>
    <ToastStack />
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import ToastStack from '@/components/general/ToastStack.vue';
import SideNav from '@/components/layout/SideNav.vue';
import TopBar from '@/components/layout/TopBar.vue';
import { setLocale } from '@/i18n';
import type { Learner, Settings } from '@/types';
import { setLearner, setSettings } from '@/utils/learner';
import { stopSpeak } from '@/utils/speech';

/**
 * The shell of every signed-in page (persistent: it stays while the pages change).
 * Sidebar and content column are direct children of #app, as in the original markup.
 */
export default defineComponent({
    name: 'AppLayout',

    components: { SideNav, ToastStack, TopBar },

    data() {
        return {
            menuOpen: false,
        };
    },

    computed: {
        path(): string {
            return this.$page.url.split(/[?#]/)[0];
        },
    },

    watch: {
        // A new page (not a reload of the same one): start at the top, with the
        // phone menu closed and nothing still being read aloud.
        path(): void {
            window.scrollTo(0, 0);
            this.menuOpen = false;
            stopSpeak();
        },

        // The phone menu slides in when <body> has "menu-open" (layout.css).
        menuOpen(open: boolean): void {
            document.body.classList.toggle('menu-open', open);
        },

        '$page.props.locale': {
            immediate: true,
            handler(locale: string): void {
                setLocale(locale);
                document.documentElement.lang = locale;
            },
        },

        '$page.props.learner': {
            immediate: true,
            handler(learner: Learner | null): void {
                if (learner) {
                    setLearner(learner);
                }
            },
        },

        '$page.props.settings': {
            immediate: true,
            handler(settings: Settings | null): void {
                if (settings) {
                    setSettings(settings);
                }
            },
        },
    },

    beforeUnmount() {
        document.body.classList.remove('menu-open');
    },
});
</script>
