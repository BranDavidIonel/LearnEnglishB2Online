<template>
    <div class="auth">
        <div class="auth-lang">
            <LanguageSwitcher />
        </div>
        <div class="auth-box fade-in">
            <slot />
        </div>
    </div>
    <ToastStack />
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import ToastStack from '@/components/general/ToastStack.vue';
import LanguageSwitcher from '@/components/layout/LanguageSwitcher.vue';
import { setLocale } from '@/i18n';

/** The login page: the form in the middle of the screen, the language switch in the corner. */
export default defineComponent({
    name: 'AuthLayout',

    components: { LanguageSwitcher, ToastStack },

    watch: {
        '$page.props.locale': {
            immediate: true,
            handler(locale: string): void {
                setLocale(locale);
                document.documentElement.lang = locale;
            },
        },
    },

    mounted() {
        // There are no settings before the login: the default theme, also after a logout.
        document.documentElement.dataset.theme = 'dark';
    },
});
</script>
