<template>
    <Head :title="$t('Home')" />

    <section
        class="rounded-2xl border border-[#2a3560] bg-[#151c36] p-6 shadow-[0_10px_30px_rgba(0,0,0,0.35)] sm:p-8"
    >
        <h1 class="text-2xl font-semibold tracking-tight">English Levels</h1>
        <p class="mt-2 text-[#97a3c9]">
            {{
                $t(
                    'Your English course, from A1 to B2. The lessons are on their way.',
                )
            }}
        </p>
    </section>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineComponent } from 'vue';

/** Start page placeholder (HomeController), replaced by the real home page. */
export default defineComponent({
    name: 'HomePage',

    components: { Head },
});
</script>
