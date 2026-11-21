<template>
    <div
        class="flex items-center gap-0.5 rounded-lg border border-white/10 bg-white/5 p-0.5"
        role="group"
        :aria-label="$t('Language')"
    >
        <Languages class="mx-1.5 hidden size-4 text-slate-400 sm:block" />
        <button
            v-for="code in $page.props.locales"
            :key="code"
            type="button"
            class="rounded-md px-2.5 py-1.5 text-xs font-semibold tracking-wide uppercase transition"
            :class="
                code === $page.props.locale
                    ? 'bg-brand-500 text-white shadow-sm'
                    : 'text-slate-400 hover:bg-white/10 hover:text-white'
            "
            :disabled="switching"
            @click="switchTo(code)"
        >
            {{ code }}
        </button>
    </div>
</template>

<script lang="ts">
import { Languages } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import localeRoutes from '@/routes/locale';

/** EN / RO switch. The server keeps the choice in the session and re-renders the page. */
export default defineComponent({
    name: 'LanguageSwitcher',

    components: { Languages },

    data() {
        return {
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
