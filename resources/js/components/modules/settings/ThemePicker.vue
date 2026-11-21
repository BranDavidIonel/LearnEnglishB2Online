<template>
    <div class="theme-grid">
        <button
            v-for="theme in THEMES"
            :key="theme.id"
            class="theme-card"
            :class="{ on: theme.id === $settings.theme }"
            :aria-pressed="theme.id === $settings.theme"
            @click="choose(theme.id)"
        >
            <span
                class="theme-preview"
                :style="{ background: theme.swatch[0] }"
            >
                <span
                    class="theme-panel"
                    :style="{ background: theme.swatch[1] }"
                >
                    <i :style="{ background: theme.swatch[2] }" />
                    <i />
                    <i />
                </span>
            </span>
            <strong>{{ $t(theme.name) }}</strong>
            <small class="muted">
                {{
                    theme.id === $settings.theme ? $t('✓ active') : $t('choose')
                }}
            </small>
        </button>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { updateSettings } from '@/api/progress';
import type { ThemeId } from '@/types';
import { setSettings } from '@/utils/learner';
import { THEMES } from '@/utils/themes';

/** The four colour themes as small previews; the chosen one applies at once and is saved. */
export default defineComponent({
    name: 'ThemePicker',

    data() {
        return {
            THEMES,
        };
    },

    methods: {
        choose(theme: ThemeId): void {
            setSettings({ ...this.$settings, theme });
            void updateSettings({ theme }).catch(() => null);
        },
    },
});
</script>
