<template>
    <header class="top">
        <button
            type="button"
            class="icon-btn menu-btn"
            :title="$t('Menu')"
            :aria-label="$t('Menu')"
            @click="$emit('toggle-menu')"
        >
            ☰
        </button>
        <div class="grow"></div>
        <div class="status">
            <LanguageSwitcher />
            <button
                type="button"
                class="pill theme-btn"
                :title="
                    $t('Theme: :name — click for the next one', {
                        name: $t(theme.name),
                    })
                "
                @click="nextTheme"
            >
                🎨
            </button>
            <Link
                v-if="$learner.level"
                :href="learnUrl"
                class="pill"
                :style="{ '--c': $learner.level.color }"
                :title="$t('The level you are working on')"
            >
                {{ $learner.level.name }}
            </Link>
            <Link :href="progressUrl" class="pill" :title="$t('Day streak')">
                🔥 {{ $learner.streak }}
            </Link>
            <Link
                :href="progressUrl"
                class="pill"
                :title="$t('XP today / goal')"
            >
                ⚡ {{ $learner.xpToday }}/{{ $learner.goal }}
            </Link>
        </div>
    </header>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import { updateSettings } from '@/api/progress';
import LanguageSwitcher from '@/components/layout/LanguageSwitcher.vue';
import { learn, progress } from '@/routes';
import { setSettings } from '@/utils/learner';
import type { ThemeInfo } from '@/utils/themes';
import { nextTheme, themeInfo } from '@/utils/themes';

/** The top bar: menu button (phone), language, theme, and the level, streak and XP pills. */
export default defineComponent({
    name: 'TopBar',

    components: { LanguageSwitcher, Link },

    emits: ['toggle-menu'],

    computed: {
        theme(): ThemeInfo {
            return themeInfo(this.$settings.theme);
        },

        learnUrl(): string {
            return learn().url;
        },

        progressUrl(): string {
            return progress().url;
        },
    },

    methods: {
        /** Switches to the next theme at once and saves it; goes back if the server refuses it. */
        nextTheme(): void {
            const before = this.$settings;
            const theme = nextTheme(before.theme).id;

            setSettings({ ...before, theme });
            updateSettings({ theme }).catch(() => setSettings(before));
        },
    },
});
</script>
