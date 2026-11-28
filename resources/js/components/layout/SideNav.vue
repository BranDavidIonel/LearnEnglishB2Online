<template>
    <aside class="side">
        <BrandMark :href="homeUrl" />
        <nav class="nav">
            <Link
                v-for="item in items"
                :key="item.id"
                :href="item.href"
                class="nav-item"
                :class="{ on: item.id === active }"
            >
                <span class="nav-icon">{{ item.icon }}</span>
                <span class="nav-label">{{ item.label }}</span>
                <span v-if="item.badge" class="nav-badge">{{
                    item.badge
                }}</span>
            </Link>
            <div class="nav-gap"></div>
            <Link
                :href="settingsUrl"
                class="nav-item"
                :class="{ on: active === 'settings' }"
            >
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">{{ $t('Settings') }}</span>
            </Link>
            <Link :href="logoutUrl" method="post" as="button" class="nav-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-label">{{ $t('Log out') }}</span>
            </Link>
        </nav>
    </aside>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import BrandMark from '@/components/layout/BrandMark.vue';
import {
    cambridge,
    flashcards,
    games,
    grammar,
    help,
    home,
    learn,
    logout,
    mistakes,
    placement,
    progress,
    settings,
    speaking,
    videos,
    words,
} from '@/routes';

type MenuId =
    | 'home'
    | 'learn'
    | 'speak'
    | 'cards'
    | 'words'
    | 'grammar'
    | 'games'
    | 'videos'
    | 'mistakes'
    | 'placement'
    | 'cambridge'
    | 'progress'
    | 'help'
    | 'settings';

type MenuItem = {
    id: MenuId;
    href: string;
    icon: string;
    label: string;
    badge: number;
};

// Which menu item a page belongs to, by its path. Several pages share one item, as in the
// original: a unit, its steps and its word bank, and the level exams are all under "Learn".
const SECTIONS: [RegExp, MenuId][] = [
    [/^\/$/, 'home'],
    [/^\/(learn|units|exams)(\/|$)/, 'learn'],
    [/^\/placement$/, 'placement'],
    [/^\/mistakes$/, 'mistakes'],
    [/^\/speaking(\/|$)/, 'speak'],
    [/^\/flashcards$/, 'cards'],
    [/^\/words(\/|$)/, 'words'],
    [/^\/grammar(\/|$)/, 'grammar'],
    [/^\/videos$/, 'videos'],
    [/^\/games(\/|$)/, 'games'],
    [/^\/cambridge(\/|$)/, 'cambridge'],
    [/^\/progress$/, 'progress'],
    [/^\/help(\/|$)/, 'help'],
    [/^\/settings$/, 'settings'],
];

/** The sidebar: logo, the twelve parts of the app with their badges, Help, then Settings and Log out at the bottom. */
export default defineComponent({
    name: 'SideNav',

    components: { BrandMark, Link },

    computed: {
        active(): MenuId | null {
            const path = this.$page.url.split(/[?#]/)[0];

            return (
                SECTIONS.find(([pattern]) => pattern.test(path))?.[1] ?? null
            );
        },

        items(): MenuItem[] {
            const due = this.$learner.due;

            return [
                this.item('home', home().url, '🏠', this.$t('Home')),
                this.item('learn', learn().url, '🎓', this.$t('Learn')),
                this.item('speak', speaking().url, '🗣️', this.$t('Speaking')),
                this.item(
                    'cards',
                    flashcards().url,
                    '🗂️',
                    this.$t('Flashcards'),
                    due.words,
                ),
                this.item(
                    'words',
                    words().url,
                    '📖',
                    this.$t('Words'),
                    due.verbs,
                ),
                this.item('grammar', grammar().url, '📐', this.$t('Grammar')),
                this.item('games', games().url, '🎮', this.$t('Games')),
                this.item('videos', videos().url, '🎬', this.$t('Videos')),
                this.item(
                    'mistakes',
                    mistakes().url,
                    '🩹',
                    this.$t('My mistakes'),
                    due.mistakes,
                ),
                this.item(
                    'placement',
                    placement().url,
                    '🧭',
                    this.$t('Placement test'),
                ),
                this.item('cambridge', cambridge().url, '📝', 'Cambridge'),
                this.item(
                    'progress',
                    progress().url,
                    '📊',
                    this.$t('Progress'),
                ),
                this.item('help', help().url, '❓', this.$t('Help')),
            ];
        },

        homeUrl(): string {
            return home().url;
        },

        settingsUrl(): string {
            return settings().url;
        },

        logoutUrl(): string {
            return logout().url;
        },
    },

    methods: {
        item(
            id: MenuId,
            href: string,
            icon: string,
            label: string,
            badge = 0,
        ): MenuItem {
            return { id, href, icon, label, badge };
        },
    },
});
</script>
