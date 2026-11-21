<template>
    <div class="min-h-screen bg-[#0b1020] text-[#e9edfb]">
        <!-- Placeholder shell: the real navigation replaces this header. -->
        <header class="border-b border-[#2a3560] bg-[#0f1630]">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6"
            >
                <Link :href="homeRoute().url">
                    <BrandMark />
                </Link>

                <div class="flex items-center gap-3">
                    <LanguageSwitcher />
                    <Link
                        :href="logoutRoute().url"
                        method="post"
                        as="button"
                        class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm text-[#97a3c9] transition hover:bg-white/10 hover:text-white"
                    >
                        <LogOut class="size-4" />
                        <span class="hidden sm:inline">{{
                            $t('Log out')
                        }}</span>
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
            <slot />
        </main>
    </div>
</template>

<script lang="ts">
import { LogOut } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import BrandMark from '@/components/layout/BrandMark.vue';
import LanguageSwitcher from '@/components/layout/LanguageSwitcher.vue';
import { setLocale } from '@/i18n';
import { home, logout } from '@/routes';

/** App shell of every signed-in page (placeholder, rebuilt with the real navigation). */
export default defineComponent({
    name: 'AppLayout',

    components: { BrandMark, LanguageSwitcher, Link, LogOut },

    data() {
        return {
            homeRoute: home,
            logoutRoute: logout,
        };
    },

    watch: {
        // Keep the language of the page in sync with the one chosen on the server.
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
