<template>
    <div
        class="relative flex min-h-screen items-center justify-center bg-[#0b1020] px-4 py-12 text-[#e9edfb]"
    >
        <div class="w-full max-w-sm">
            <slot />
        </div>

        <div class="absolute top-4 right-4">
            <LanguageSwitcher />
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import LanguageSwitcher from '@/components/layout/LanguageSwitcher.vue';
import { setLocale } from '@/i18n';

/**
 * Centered layout of the login page, in the dark colors of the first version
 * (background #0b1020, cards #151c36, indigo #6366f1).
 */
export default defineComponent({
    name: 'AuthLayout',

    components: { LanguageSwitcher },

    watch: {
        '$page.props.locale': {
            immediate: true,
            handler(locale: string) {
                setLocale(locale);
                document.documentElement.lang = locale;
            },
        },
    },
});
</script>
