<template>
    <SideNav />
    <div class="content">
        <TopBar @toggle-menu="menuOpen = !menuOpen" />
        <main id="main">
            <slot />
        </main>
    </div>
    <ToastStack />
    <WordLookup
        v-if="tapped"
        :key="tapped.id"
        :word="tapped.word"
        :sentence="tapped.sentence"
        @changed="changed = true"
        @close="closeLookup"
    />
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import ToastStack from '@/components/general/ToastStack.vue';
import SideNav from '@/components/layout/SideNav.vue';
import TopBar from '@/components/layout/TopBar.vue';
import WordLookup from '@/components/modules/words/WordLookup.vue';
import { setLocale } from '@/i18n';
import type { Learner, Settings } from '@/types';
import { setLearner, setSettings } from '@/utils/learner';
import { stopSpeak } from '@/utils/speech';
import { TAP_WORD, tapTarget } from '@/utils/tapWords';
import type { OpenWord } from '@/utils/wordLookup';
import { closeWord, openWord, wordLookup } from '@/utils/wordLookup';

/**
 * The shell of every signed-in page (persistent: it stays while the pages change).
 * Sidebar and content column are direct children of #app, as in the original markup.
 * It also holds the one word window: a tap on a tappable word anywhere opens it.
 */
export default defineComponent({
    name: 'AppLayout',

    components: { SideNav, ToastStack, TopBar, WordLookup },

    data() {
        return {
            menuOpen: false,
            // The word tapped in the page, lit while its window is open; the focus goes back to it.
            tappedWord: null as HTMLElement | null,
            // A word's state changed in the open window.
            changed: false,
        };
    },

    computed: {
        tapped(): OpenWord | null {
            return wordLookup.current;
        },

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
            this.closeLookup(false);
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

    mounted() {
        // Capture phase: the tap is handled before the word's container sees the click, so a
        // bubble that plays its line or a card that turns does not react to it.
        document.addEventListener('click', this.onTap, true);
    },

    beforeUnmount() {
        document.removeEventListener('click', this.onTap, true);
        document.body.classList.remove('menu-open');
        closeWord();
    },

    methods: {
        onTap(event: MouseEvent): void {
            const word =
                event.target instanceof Element
                    ? event.target.closest<HTMLElement>(`.${TAP_WORD}`)
                    : null;

            // Selecting text with the mouse is not a tap.
            if (!word || !window.getSelection()?.isCollapsed) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const target = tapTarget(word);

            if (!target) {
                return;
            }

            stopSpeak();

            // A word tapped inside the window opens in it; the focus still goes back to the page.
            if (!word.closest('.lookup')) {
                this.tappedWord?.classList.remove('on');
                this.tappedWord = word;
                word.classList.add('on');
            }

            openWord(target);
        },

        /** Closes the window and gives the focus back to the word it was opened from. */
        closeLookup(focus = true): void {
            const word = this.tappedWord;

            closeWord(this.changed);
            this.changed = false;
            this.tappedWord = null;

            if (word) {
                word.classList.remove('on');

                if (focus && word.isConnected) {
                    word.tabIndex = -1;
                    word.focus({ preventScroll: true });
                }
            }
        },
    },
});
</script>
