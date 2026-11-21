<template>
    <div class="lang-switch" role="group" :aria-label="$t('App language')">
        <button
            v-for="code in $page.props.locales"
            :key="code"
            type="button"
            :class="{ on: code === $page.props.locale }"
            :title="LANGUAGE_NAMES[code] ?? code"
            :disabled="switching"
            @click="switchTo(code)"
        >
            {{ code.toUpperCase() }}
        </button>
    </div>
</template>

<script lang="ts">
import { router } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import localeRoutes from '@/routes/locale';

// Each language is named in itself, whatever the interface language is.
const LANGUAGE_NAMES: Record<string, string> = {
    en: 'English',
    ro: 'Română',
};

/** EN / RO switch of the top bar. The server keeps the choice and sends the page again in that language. */
export default defineComponent({
    name: 'LanguageSwitcher',

    data() {
        return {
            LANGUAGE_NAMES,
            switching: false,
        };
    },

    methods: {
        switchTo(locale: string): void {
            if (locale === this.$page.props.locale) {
                return;
            }

            router.put(
                localeRoutes.update().url,
                { locale },
                {
                    preserveScroll: true,
                    onStart: () => (this.switching = true),
                    onFinish: () => (this.switching = false),
                },
            );
        },
    },
});
</script>
